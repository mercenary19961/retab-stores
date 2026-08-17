<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderCancelledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Customer cancellation from the storefront.
 *
 * An explicit requirement of the client brief that had no route at all:
 * `OrderConfirmationService::cancelByCustomer()` is named for this, but the
 * only caller was the admin panel.
 */
class CustomerCancelTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'RTB-CANCEL-1',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::BankTransfer,
            'subtotal' => 100,
            'total' => 125,
        ], $overrides));
    }

    public function test_a_customer_cancels_their_own_unconfirmed_order(): void
    {
        Notification::fake();
        $order = $this->order();

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->post("/orders/{$order->order_number}/cancel")
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNotNull($order->cancelled_at);
    }

    /** An unpaid card order is still pre-confirmation, so it is cancellable too. */
    public function test_an_unpaid_gateway_order_is_cancellable(): void
    {
        Notification::fake();
        $order = $this->order([
            'status' => OrderStatus::PendingPayment,
            'payment_method' => PaymentMethod::Card,
        ]);

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->post("/orders/{$order->order_number}/cancel");

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    /**
     * 🔑 The brief's actual rule: only BEFORE the admin confirms. The window is
     * owned by OrderStatus::isCancellableByCustomer(), never restated in the
     * controller — restating it is how the admin panel ended up offering a
     * Cancel button in the one state the service always rejected.
     */
    public function test_a_confirmed_order_can_no_longer_be_cancelled(): void
    {
        $order = $this->order(['status' => OrderStatus::Confirmed]);

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->post("/orders/{$order->order_number}/cancel")
            ->assertSessionHas('error', __('messages.orders.not_cancellable'));

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }

    /** ⚠️ That refusal reaches an Arabic-first storefront, so it must localize. */
    public function test_the_refusal_is_localized(): void
    {
        $order = $this->order(['status' => OrderStatus::Shipped]);

        $this->withSession(['placed_orders' => [$order->order_number], 'locale' => 'ar'])
            ->withHeaders(['Accept-Language' => 'ar'])
            ->post("/orders/{$order->order_number}/cancel");

        // The Arabic string differs from the English one; if the literal were
        // still hardcoded these would be identical.
        $this->assertNotSame(
            trans('messages.orders.not_cancellable', [], 'en'),
            trans('messages.orders.not_cancellable', [], 'ar'),
        );
    }

    public function test_a_stranger_cannot_cancel_someone_elses_order(): void
    {
        $order = $this->order();

        // No session entry, not signed in as the owner.
        $this->post("/orders/{$order->order_number}/cancel")->assertForbidden();

        $this->assertSame(OrderStatus::AwaitingConfirmation, $order->fresh()->status);
    }

    public function test_the_signed_in_owner_can_cancel_without_the_session_entry(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->order(['user_id' => $user->id]);

        $this->actingAs($user)->post("/orders/{$order->order_number}/cancel");

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    /**
     * Staff have to find out — someone may already be picking stock. The bell is
     * the channel that works with no queue worker and no Meta credentials, which
     * is why it is not left to the WhatsApp fan-out alone.
     */
    public function test_staff_are_notified(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order();

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->post("/orders/{$order->order_number}/cancel");

        Notification::assertSentTo($admin, OrderCancelledNotification::class);
    }

    /**
     * 🔴 `payment_status` stays `pending` on a cancelled bank transfer (there is
     * nothing to release), so the page kept rendering the IBAN and telling the
     * customer to transfer money for an order that no longer exists.
     */
    public function test_a_cancelled_bank_transfer_stops_showing_the_iban(): void
    {
        Notification::fake();
        $order = $this->order();

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->get("/orders/{$order->order_number}")
            ->assertInertia(fn (Assert $page) => $page->has('bank'));

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->post("/orders/{$order->order_number}/cancel");

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->get("/orders/{$order->order_number}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('bank', null)
                ->where('order.status', 'cancelled')
                ->where('canCancel', false));
    }

    /** The button is driven by the same enum the service enforces. */
    public function test_the_order_page_ships_the_cancel_flag(): void
    {
        $open = $this->order();
        $closed = $this->order(['order_number' => 'RTB-CANCEL-2', 'status' => OrderStatus::Delivered]);

        $this->withSession(['placed_orders' => [$open->order_number, $closed->order_number]])
            ->get("/orders/{$open->order_number}")
            ->assertInertia(fn (Assert $page) => $page->where('canCancel', true));

        $this->withSession(['placed_orders' => [$open->order_number, $closed->order_number]])
            ->get("/orders/{$closed->order_number}")
            ->assertInertia(fn (Assert $page) => $page->where('canCancel', false));
    }
}
