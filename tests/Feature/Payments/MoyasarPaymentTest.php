<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\NormalizedPayment;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoyasarPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        return Order::create([
            'order_number' => 'RTB-PAY-1',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'gateway_reference' => 'inv_1',
            'subtotal' => 100,
            'total' => 125, // → 12500 halalas expected
        ]);
    }

    /** A PaymentService backed by a fake gateway that returns $payment from fetch. */
    private function serviceReturning(NormalizedPayment $payment): PaymentService
    {
        $gateway = new class($payment) implements PaymentGateway
        {
            public function __construct(private NormalizedPayment $payment) {}

            public function createInvoice(Order $order): array
            {
                return ['url' => 'https://pay.test/inv_1', 'invoice_id' => 'inv_1', 'raw' => []];
            }

            public function fetchPayment(string $paymentId): NormalizedPayment
            {
                return $this->payment;
            }

            public function fetchInvoice(string $invoiceId): array
            {
                return ['status' => 'paid', 'payments' => [$this->payment], 'raw' => []];
            }

            public function verifyWebhookToken(?string $token): bool
            {
                return $token === 'secret';
            }

            public function refundPayment(string $paymentId, int $amount): NormalizedPayment
            {
                return $this->payment;
            }
        };

        return new PaymentService($gateway);
    }

    public function test_verified_paid_payment_marks_order_paid_and_awaiting_confirmation(): void
    {
        $order = $this->makeOrder();
        $payment = new NormalizedPayment(
            id: 'pay_1', status: 'paid', amount: 12500, currency: 'SAR',
            invoiceId: 'inv_1', orderId: $order->id,
        );

        $this->serviceReturning($payment)->confirmFromGateway('pay_1');

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::AwaitingConfirmation, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'type' => 'capture',
            'status' => 'succeeded',
            'gateway_transaction_id' => 'pay_1',
        ]);
    }

    public function test_amount_mismatch_is_not_fulfilled(): void
    {
        $order = $this->makeOrder();
        $payment = new NormalizedPayment(
            id: 'pay_2', status: 'paid', amount: 9900, currency: 'SAR', // wrong amount
            invoiceId: 'inv_1', orderId: $order->id,
        );

        $this->serviceReturning($payment)->confirmFromGateway('pay_2');

        $order->refresh();
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
    }

    public function test_confirmation_is_idempotent(): void
    {
        $order = $this->makeOrder();
        $payment = new NormalizedPayment(
            id: 'pay_3', status: 'paid', amount: 12500, currency: 'SAR',
            invoiceId: 'inv_1', orderId: $order->id,
        );
        $service = $this->serviceReturning($payment);

        $service->confirmFromGateway('pay_3');
        $service->confirmFromGateway('pay_3'); // duplicate delivery

        $this->assertSame(1, Payment::where('gateway_transaction_id', 'pay_3')->count());
        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
    }
}
