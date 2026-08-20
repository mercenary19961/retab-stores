<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\Tamara\TamaraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Recovery for a Tamara hold that lapsed before staff confirmed the order.
 *
 * 🔑 The whole point is that the customer never has to re-order: the order still
 * exists with its items and address, so the recovery reopens THAT order for
 * payment rather than asking them to rebuild a basket.
 */
class LapsedAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'a'.uniqid().'@test.com',
            'password' => bcrypt('x'), 'role' => 'admin',
        ]);
    }

    private function heldOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'RTB-'.uniqid(),
            'customer_name' => 'نورة',
            'customer_phone' => '+966500000001',
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Authorized,
            'payment_method' => PaymentMethod::Tamara,
            'payment_gateway' => 'tamara',
            'gateway_reference' => 'tamara-'.uniqid(),
            'subtotal' => 100, 'shipping_fee' => 25, 'total' => 125,
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'locale' => 'ar',
        ], $overrides));
    }

    /** A TamaraService whose remote lookup returns a fixed status. */
    private function serviceReporting(string $status): TamaraService
    {
        return new class($status) extends TamaraService
        {
            public function __construct(private string $remoteStatus)
            {
                // Deliberately skips the parent constructor: this double never
                // touches the client, so it needs none of its dependencies.
            }

            protected function remoteOrder(string $reference): array
            {
                return ['status' => $this->remoteStatus];
            }
        };
    }

    // ------------------------------------------------------------------ detection

    public function test_a_lapsed_hold_is_reopened_for_payment_rather_than_cancelled(): void
    {
        $order = $this->heldOrder();

        $status = $this->serviceReporting('expired')->reconcileLapsed($order);

        $this->assertSame('expired', $status);
        $order->refresh();
        // Failed + PendingPayment is precisely the shape CheckoutController::pay
        // already knows how to restart — cancelling would destroy an order the
        // customer still wants.
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertTrue($order->isAwaitingGatewayPayment());
        $this->assertDatabaseHas('order_activities', ['order_id' => $order->id, 'type' => 'payment_lapsed']);
    }

    public function test_an_unrecognised_remote_status_leaves_the_order_alone(): void
    {
        // 🔴 The safe direction. Our 48h window is an assumption, and Tamara's
        // status vocabulary was read from docs rather than a live response — so an
        // unknown value must never be read as "dead". Marking a live order dead is
        // far worse than being slow to notice a dead one.
        $order = $this->heldOrder();

        $this->assertNull($this->serviceReporting('authorised')->reconcileLapsed($order));
        $this->assertNull($this->serviceReporting('some_new_status')->reconcileLapsed($order));

        $order->refresh();
        $this->assertSame(PaymentStatus::Authorized, $order->payment_status);
        $this->assertSame(OrderStatus::AwaitingConfirmation, $order->status);
    }

    public function test_an_already_captured_order_is_never_touched(): void
    {
        $order = $this->heldOrder(['payment_status' => PaymentStatus::Paid]);

        $this->assertNull($this->serviceReporting('expired')->reconcileLapsed($order));
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    // ----------------------------------------------------------------- signed link

    public function test_the_signed_link_resumes_payment_without_a_session(): void
    {
        // The reason the link exists: a guest who ordered days ago has no session,
        // and the order page would 403 them.
        $order = $this->heldOrder(['payment_status' => PaymentStatus::Failed, 'status' => OrderStatus::PendingPayment]);
        $url = URL::temporarySignedRoute('orders.resume', now()->addDays(3), ['order' => $order->order_number]);

        // The security property under test is that the SIGNATURE alone gets past
        // authorisation. Where it lands afterwards depends on Tamara credentials
        // the test suite does not have, so assert what is actually determinate:
        // not a 403, and a redirect rather than an error page.
        $response = $this->get($url);
        $this->assertNotSame(403, $response->getStatusCode(), 'a validly signed link must not be refused');
        $response->assertRedirect();
    }

    public function test_an_unsigned_or_tampered_link_is_refused(): void
    {
        $order = $this->heldOrder(['payment_status' => PaymentStatus::Failed, 'status' => OrderStatus::PendingPayment]);

        // No signature at all.
        $this->get("/orders/{$order->order_number}/resume")->assertForbidden();

        // A valid signature for a DIFFERENT order, replayed at this one.
        $other = $this->heldOrder(['payment_status' => PaymentStatus::Failed, 'status' => OrderStatus::PendingPayment]);
        $signed = URL::temporarySignedRoute('orders.resume', now()->addDays(3), ['order' => $other->order_number]);
        $swapped = str_replace($other->order_number, $order->order_number, $signed);
        $this->get($swapped)->assertForbidden();
    }

    public function test_an_expired_signature_is_refused(): void
    {
        $order = $this->heldOrder(['payment_status' => PaymentStatus::Failed, 'status' => OrderStatus::PendingPayment]);
        $url = URL::temporarySignedRoute('orders.resume', now()->addDays(3), ['order' => $order->order_number]);

        $this->travel(4)->days();
        $this->get($url)->assertForbidden();
    }

    public function test_a_link_to_an_already_settled_order_does_not_restart_anything(): void
    {
        $order = $this->heldOrder(['payment_status' => PaymentStatus::Paid, 'status' => OrderStatus::Confirmed]);
        $url = URL::temporarySignedRoute('orders.resume', now()->addDays(3), ['order' => $order->order_number]);

        $this->get($url)->assertRedirect(route('home'));
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
    }

    // ---------------------------------------------------------------- admin action

    public function test_the_admin_action_is_offered_only_when_it_can_do_something(): void
    {
        $admin = $this->admin();

        // Still held — nothing to resume yet.
        $held = $this->heldOrder();
        $this->actingAs($admin)->post("/admin/orders/{$held->order_number}/payment-link")
            ->assertSessionHas('error');

        // Lapsed and reopened — this is the case the button exists for.
        $lapsed = $this->heldOrder(['payment_status' => PaymentStatus::Failed, 'status' => OrderStatus::PendingPayment]);
        $this->actingAs($admin)->post("/admin/orders/{$lapsed->order_number}/payment-link")
            ->assertSessionHas('success');
        $this->assertDatabaseHas('order_activities', ['order_id' => $lapsed->id, 'type' => 'payment_link_sent']);
    }
}
