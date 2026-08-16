<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payments\NormalizedPayment;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where a hosted gateway returns the customer after payment.
 *
 * 🔴 Neither return URL was ever routed: Tamara sends the shopper to
 * `/checkout/result` and Moyasar sent them to `/checkout/success`, and both
 * 404'd. So a customer paid, got redirected, and landed on an error page — and
 * the webhook was left as the ONLY thing able to advance the order.
 */
class GatewayReturnTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'RTB-RETURN-1',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Card,
            'payment_gateway' => 'moyasar',
            'gateway_reference' => 'inv_1',
            'payment_url' => 'https://pay.test/inv_1',
            'subtotal' => 100,
            'total' => 125,
        ], $overrides));
    }

    /**
     * A gateway whose invoice reports one PAID payment, so `reconcile()` has
     * something to confirm the order from.
     */
    private function fakePaidGateway(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new class implements PaymentGateway
        {
            public function createInvoice(Order $order): array
            {
                return ['url' => 'https://pay.test/inv_1', 'invoice_id' => 'inv_1', 'raw' => []];
            }

            public function fetchPayment(string $paymentId): NormalizedPayment
            {
                return new NormalizedPayment($paymentId, 'paid', 12500, 'SAR', null, null, 'inv_1', null, null, []);
            }

            public function fetchInvoice(string $invoiceId): array
            {
                return [
                    'status' => 'paid',
                    'payments' => [new NormalizedPayment('pay_1', 'paid', 12500, 'SAR', null, null, 'inv_1', null, null, [])],
                    'raw' => [],
                ];
            }

            public function verifyWebhookToken(?string $token): bool
            {
                return false;
            }

            public function refundPayment(string $paymentId, int $amount): NormalizedPayment
            {
                return new NormalizedPayment($paymentId, 'refunded', $amount, 'SAR', null, null, 'inv_1', null, null, []);
            }
        });
    }

    /** 🔴 The bug itself: this used to be a 404 for every paying customer. */
    public function test_the_return_url_is_routed_and_lands_on_the_order(): void
    {
        $order = $this->order();

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->get('/checkout/result?status=success')
            ->assertRedirect(route('orders.show', $order->order_number));
    }

    /**
     * 🔑 The point of the whole route: the customer's own return confirms the
     * order, so a webhook that never arrives degrades instead of stranding a
     * paid order at `pending_payment`.
     */
    public function test_the_return_confirms_a_card_order_without_any_webhook(): void
    {
        $this->fakePaidGateway();
        $order = $this->order();

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->get('/checkout/result?status=success')
            ->assertRedirect(route('orders.show', $order->order_number));

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    /**
     * Identity comes from OUR session first, because it cannot be influenced by
     * the query string. A top-level GET redirect carries a SameSite=Lax cookie,
     * so it survives the trip out to the gateway and back.
     */
    public function test_a_forged_reference_cannot_redirect_a_visitor_off_their_own_order(): void
    {
        $mine = $this->order();
        $theirs = $this->order(['order_number' => 'RTB-RETURN-2', 'gateway_reference' => 'inv_someone_else']);

        $this->withSession(['placed_orders' => [$mine->order_number]])
            ->get('/checkout/result?orderId=inv_someone_else')
            ->assertRedirect(route('orders.show', $mine->order_number));

        $this->assertSame(PaymentStatus::Pending, $theirs->fresh()->payment_status);
    }

    /** Fallback for a lost session: Tamara returns its own order id. */
    public function test_a_lost_session_falls_back_to_the_gateway_reference(): void
    {
        $order = $this->order([
            'payment_method' => PaymentMethod::Tamara,
            'payment_gateway' => 'tamara',
            'gateway_reference' => 'tamara-order-abc',
        ]);

        // No `placed_orders` in the session at all.
        $this->get('/checkout/result?orderId=tamara-order-abc')
            ->assertRedirect(route('orders.show', $order->order_number))
            // Access is granted for the confirmation page, or the customer would
            // land on their own order and get a 403 from assertOwns().
            ->assertSessionHas('placed_orders', [$order->order_number]);
    }

    /**
     * An unusable reference must not 500 or leak; it sends the visitor somewhere
     * sensible with an explanation.
     */
    public function test_an_unmatchable_return_is_handled(): void
    {
        $this->get('/checkout/result?orderId=does-not-exist')
            ->assertRedirect(route('home'))
            ->assertSessionHas('error');
    }

    /**
     * ⚠️ An empty `gateway_reference` must not match an order whose column is
     * also empty — otherwise a bare `/checkout/result` with no session would
     * hand out whichever order happened to have no reference yet.
     */
    public function test_a_missing_reference_does_not_match_an_order_without_one(): void
    {
        $this->order(['gateway_reference' => null, 'payment_url' => null]);

        $this->get('/checkout/result')
            ->assertRedirect(route('home'))
            ->assertSessionHas('error');
    }

    /** A cancelled or declined payment lands on the order page with the pay button. */
    public function test_a_cancelled_payment_explains_itself_and_keeps_the_order_payable(): void
    {
        $order = $this->order();

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->get('/checkout/result?status=cancel')
            ->assertRedirect(route('orders.show', $order->order_number))
            ->assertSessionHas('error', __('messages.payment.not_completed'));

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    /**
     * Already settled by the webhook: no outbound call, and the customer is not
     * told their payment failed.
     */
    public function test_an_already_paid_order_is_not_re_confirmed(): void
    {
        $order = $this->order(['payment_status' => PaymentStatus::Paid, 'status' => OrderStatus::AwaitingConfirmation]);

        // No gateway is bound, so any outbound call would blow up here.
        $this->withSession(['placed_orders' => [$order->order_number]])
            ->get('/checkout/result?status=success')
            ->assertRedirect(route('orders.show', $order->order_number))
            ->assertSessionHas('success');
    }

    /** A gateway failure must not cost the customer their order page. */
    public function test_a_gateway_error_still_lands_the_customer_on_their_order(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new class implements PaymentGateway
        {
            public function createInvoice(Order $order): array
            {
                throw new \RuntimeException('gateway down');
            }

            public function fetchPayment(string $paymentId): NormalizedPayment
            {
                throw new \RuntimeException('gateway down');
            }

            public function fetchInvoice(string $invoiceId): array
            {
                throw new \RuntimeException('gateway down');
            }

            public function verifyWebhookToken(?string $token): bool
            {
                return false;
            }

            public function refundPayment(string $paymentId, int $amount): NormalizedPayment
            {
                throw new \RuntimeException('gateway down');
            }
        });

        $order = $this->order();

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->get('/checkout/result?status=success')
            ->assertRedirect(route('orders.show', $order->order_number));
    }

    /**
     * Both gateways must land on the ONE handler. AppServiceProvider builds
     * Moyasar's success_url from a hardcoded path string, so renaming the route
     * would silently point it at a 404 again — which is the exact bug this file
     * exists for.
     */
    public function test_moyasar_returns_to_the_same_routed_url(): void
    {
        $this->assertSame(
            rtrim((string) config('app.url'), '/').'/checkout/result',
            route('checkout.result'),
        );
    }
}
