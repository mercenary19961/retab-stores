<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payments\NormalizedPayment;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Tamara\TamaraClient;
use App\Services\Payments\Tamara\TamaraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The scheduled sweeper that re-asks the gateway about unpaid orders.
 *
 * 🔴 It is the third and only unconditional route by which a paid order becomes
 * a confirmed one. The webhook can be lost; the customer's return to
 * /checkout/result can simply never happen. When both miss, the money is at the
 * gateway and the order reads unpaid forever — so the first test here is the
 * whole reason the command exists.
 */
class ReconcilePendingPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'RTB-REC-'.uniqid(),
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Card,
            'gateway_reference' => 'inv_1',
            'subtotal' => 100,
            'total' => 125,   // → 12500 halalas
        ], $overrides));

        // Past the 15-minute grace by default, so tests that care about age say so.
        $order->forceFill(['created_at' => $overrides['created_at'] ?? now()->subHour()])->save();

        return $order->refresh();
    }

    /** Binds a PaymentService whose gateway reports the invoice as paid. */
    private function bindGatewayReporting(string $status, int $amount = 12500): void
    {
        $payment = new NormalizedPayment(
            id: 'pay_1', status: $status, amount: $amount, currency: 'SAR', invoiceId: 'inv_1',
        );

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
                return true;
            }

            public function refundPayment(string $paymentId, int $amount): NormalizedPayment
            {
                return $this->payment;
            }
        };

        $this->app->instance(PaymentService::class, new PaymentService($gateway));
    }

    /**
     * 🔴 The case the command exists for: the webhook never arrived and the
     * customer never came back, so nothing else in the system would ever have
     * noticed that this order was paid.
     */
    public function test_it_recovers_an_order_whose_webhook_and_return_both_missed(): void
    {
        $order = $this->makeOrder();
        $this->bindGatewayReporting('paid');

        $this->artisan('payments:reconcile')->assertSuccessful();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::AwaitingConfirmation, $order->status, 'a recovered order must reach the queue staff work from');
        $this->assertNotNull($order->paid_at);
    }

    /**
     * ⚠️ A race, not politeness: the webhook usually lands within seconds, so
     * checking immediately would mostly spend API calls on orders that were about
     * to settle themselves.
     */
    public function test_it_leaves_a_customer_who_may_still_be_on_the_gateway_page(): void
    {
        $order = $this->makeOrder(['created_at' => now()->subMinutes(2)]);
        $this->bindGatewayReporting('paid');

        $this->artisan('payments:reconcile')->expectsOutputToContain('Nothing to reconcile');

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    /** Past the window the answer can no longer change, so stop asking. */
    public function test_it_stops_asking_about_very_old_orders(): void
    {
        $order = $this->makeOrder(['created_at' => now()->subDays(30)]);
        $this->bindGatewayReporting('paid');

        $this->artisan('payments:reconcile');

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    /**
     * Bank transfer has no gateway to ask. Its equivalent is an admin pressing
     * "Mark transfer received", and a sweeper must never fabricate that.
     */
    public function test_it_never_touches_a_bank_transfer_order(): void
    {
        $order = $this->makeOrder([
            'payment_method' => PaymentMethod::BankTransfer,
            'gateway_reference' => null,
        ]);
        $this->bindGatewayReporting('paid');

        $this->artisan('payments:reconcile');

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    /** No reference means the customer never reached the gateway at all. */
    public function test_it_skips_an_order_that_never_reached_the_gateway(): void
    {
        $order = $this->makeOrder(['gateway_reference' => null]);
        $this->bindGatewayReporting('paid');

        $this->artisan('payments:reconcile')->expectsOutputToContain('Nothing to reconcile');

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    /** An unpaid order stays unpaid — the command reports, it does not decide. */
    public function test_an_order_the_gateway_calls_unpaid_is_left_alone(): void
    {
        $order = $this->makeOrder();
        $this->bindGatewayReporting('pending');

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    /**
     * ⚠️ A gateway blip must not fail the scheduled run, or every transient
     * outage becomes an alert nobody can act on. The log line is the record.
     */
    public function test_a_gateway_failure_does_not_fail_the_run(): void
    {
        $this->makeOrder();

        $gateway = new class implements PaymentGateway
        {
            public function createInvoice(Order $order): array
            {
                return [];
            }

            public function fetchPayment(string $paymentId): NormalizedPayment
            {
                throw new \RuntimeException('Moyasar unreachable');
            }

            public function fetchInvoice(string $invoiceId): array
            {
                throw new \RuntimeException('Moyasar unreachable');
            }

            public function verifyWebhookToken(?string $token): bool
            {
                return true;
            }

            public function refundPayment(string $paymentId, int $amount): NormalizedPayment
            {
                throw new \RuntimeException('Moyasar unreachable');
            }
        };
        $this->app->instance(PaymentService::class, new PaymentService($gateway));

        $this->artisan('payments:reconcile')->assertSuccessful();
    }

    /** Dry run reads the gateway not at all and changes nothing. */
    public function test_dry_run_changes_nothing(): void
    {
        $order = $this->makeOrder();
        $this->bindGatewayReporting('paid');

        $this->artisan('payments:reconcile --dry-run')->assertSuccessful();

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    /** Binds a TamaraService whose client reports a canned remote status. */
    private function bindTamaraReporting(string $remoteStatus): void
    {
        $client = new class('t', 'n', 'https://x') extends TamaraClient
        {
            public string $remoteStatus = 'approved';

            public function getOrder(string $orderId): array
            {
                return ['status' => $this->remoteStatus, 'total_amount' => ['amount' => 125.00, 'currency' => 'SAR']];
            }

            public function authorise(string $orderId): array
            {
                return [];
            }
        };
        $client->remoteStatus = $remoteStatus;

        $this->app->instance(TamaraService::class, new TamaraService($client));
    }

    /** The Tamara half of the same hole: an authorisation nobody told us about. */
    public function test_it_recovers_a_tamara_order_whose_webhook_missed(): void
    {
        $order = $this->makeOrder(['payment_method' => PaymentMethod::Tamara, 'payment_gateway' => 'tamara']);
        $this->bindTamaraReporting('approved');

        $this->artisan('payments:reconcile')->assertSuccessful();

        $order->refresh();
        $this->assertSame(PaymentStatus::Authorized, $order->payment_status);
        $this->assertSame(OrderStatus::AwaitingConfirmation, $order->status);
    }

    /**
     * 🔑 A declined Tamara order is RESOLVED, not recovered, and the distinction is
     * why the outcome is read off our own order rather than the service's return
     * value: confirm() answers a nullable Order whose null means "could not be
     * matched locally", which read as a truthy "settled" would report an unmatched
     * order as money found.
     */
    public function test_a_declined_tamara_order_is_reported_as_resolved_not_recovered(): void
    {
        $order = $this->makeOrder(['payment_method' => PaymentMethod::Tamara, 'payment_gateway' => 'tamara']);
        $this->bindTamaraReporting('declined');

        $this->artisan('payments:reconcile')
            ->doesntExpectOutputToContain('RECOVERED')
            ->assertSuccessful();

        $this->assertSame(PaymentStatus::Failed, $order->refresh()->payment_status);
    }
}
