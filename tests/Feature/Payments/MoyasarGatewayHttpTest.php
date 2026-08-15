<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payments\MoyasarGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises the REAL MoyasarGateway over a faked HTTP layer.
 *
 * MoyasarPaymentTest covers the order lifecycle through a hand-written fake
 * gateway, so it never touches this class's HTTP client — which is precisely how
 * the retry policy below went unnoticed. These tests pin the wire behaviour.
 */
class MoyasarGatewayHttpTest extends TestCase
{
    use RefreshDatabase;

    private function gateway(): MoyasarGateway
    {
        return new MoyasarGateway(
            secretKey: 'sk_test_x',
            baseUrl: 'https://api.moyasar.com/v1',
            currency: 'SAR',
            webhookToken: 'hook-secret',
            successUrl: 'https://retab.com.sa/checkout/success',
            callbackUrl: 'https://retab.com.sa/webhooks/moyasar',
        );
    }

    /** @return array<string, mixed> */
    private function paymentJson(string $status = 'paid'): array
    {
        return [
            'id' => 'pay_1',
            'status' => $status,
            'amount' => 12500,
            'currency' => 'SAR',
            'source' => ['type' => 'creditcard', 'company' => 'visa'],
            'metadata' => ['order_id' => '1'],
        ];
    }

    /**
     * 🔴 The worst case, and the reason the retry policy changed at all.
     *
     * Under the previous bare `retry(2, 200, throw: false)` this sent the refund
     * TWICE and then returned normally — so Moyasar refunded twice while our
     * ledger recorded one row. A lost response is indistinguishable from a lost
     * request, so the client cannot know the first one didn't land.
     */
    public function test_a_refund_is_not_re_sent_after_a_lost_connection(): void
    {
        Http::fake([
            '*/payments/pay_1/refund' => Http::sequence()
                ->pushFailedConnection('cURL error 28: Operation timed out')
                ->push($this->paymentJson('refunded'), 200),
        ]);

        $this->expectException(ConnectionException::class);

        try {
            $this->gateway()->refundPayment('pay_1', 5000);
        } finally {
            Http::assertSentCount(1);
        }
    }

    /**
     * The same ambiguity with a status code attached: a 5xx may mean the refund
     * was executed and only the response failed. Was 2 requests before.
     */
    public function test_a_refund_is_never_retried(): void
    {
        Http::fake([
            '*/payments/pay_1/refund' => Http::sequence()
                ->push(['message' => 'gateway error'], 500)
                ->push($this->paymentJson('refunded'), 200),
        ]);

        try {
            $this->gateway()->refundPayment('pay_1', 5000);
            $this->fail('Expected the failed refund to surface to the caller.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('refund pay_1 failed', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    /** Invoice creation is a write too: a retry would leave a stray unpaid invoice. */
    public function test_invoice_creation_is_never_retried(): void
    {
        Http::fake([
            '*/invoices' => Http::sequence()
                ->push(['message' => 'gateway error'], 500)
                ->push(['id' => 'inv_1', 'url' => 'https://pay.test/inv_1'], 200),
        ]);

        $order = Order::create([
            'order_number' => 'RTB-HTTP-1',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'subtotal' => 100,
            'total' => 125,
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            $this->gateway()->createInvoice($order);
        } finally {
            Http::assertSentCount(1);
        }
    }

    /** Reads are safe to repeat, so they still ride out a transient fault. */
    public function test_a_read_is_retried_on_a_transient_failure(): void
    {
        Http::fake([
            '*/payments/pay_1' => Http::sequence()
                ->push(['message' => 'unavailable'], 503)
                ->push($this->paymentJson(), 200),
        ]);

        $payment = $this->gateway()->fetchPayment('pay_1');

        $this->assertSame('paid', $payment->status);
        Http::assertSentCount(2);
    }

    /** …but not on a 4xx, where re-sending the same rejected request is pointless. */
    public function test_a_read_is_not_retried_on_a_client_error(): void
    {
        Http::fake([
            '*/payments/pay_1' => Http::sequence()
                ->push(['message' => 'not found'], 404)
                ->push($this->paymentJson(), 200),
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            $this->gateway()->fetchPayment('pay_1');
        } finally {
            Http::assertSentCount(1);
        }
    }

    /** The webhook shared secret is compared in constant time and never empty-passes. */
    public function test_webhook_token_verification(): void
    {
        $gateway = $this->gateway();

        $this->assertTrue($gateway->verifyWebhookToken('hook-secret'));
        $this->assertFalse($gateway->verifyWebhookToken('wrong'));
        $this->assertFalse($gateway->verifyWebhookToken(null));
        $this->assertFalse($gateway->verifyWebhookToken(''));
    }

    /** Halalas, not SAR — an off-by-100 here is an off-by-100 charge. */
    public function test_amounts_convert_to_halalas(): void
    {
        $gateway = $this->gateway();

        $this->assertSame(12500, $gateway->toMinorUnits(125.00));
        $this->assertSame(1150, $gateway->toMinorUnits(11.50));
        // Float noise must round, not truncate: 0.1+0.2 style drift would give 1009.
        $this->assertSame(1010, $gateway->toMinorUnits(10.10));
    }
}
