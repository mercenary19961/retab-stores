<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Shipping\DeliveryOption;
use App\Services\Shipping\NormalizedShipment;
use App\Services\Shipping\ShippingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * The admin shipping actions: carrier choice, recalling a shipment, and the
 * order-cancel gate.
 *
 * The gateway is mocked rather than faked over HTTP — these tests are about the
 * controller/service contract (which carrier id reaches the provider, what the
 * order looks like afterwards), not about OTO's wire format, which
 * OtoClientTest and OtoWebhookTest cover.
 */
class OrderShippingActionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'RTB-SHIP-1',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Jeddah'],
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 100,
            'shipping_fee' => 25,
            'total' => 125,
        ], $overrides));
    }

    /** @return Mockery\MockInterface&ShippingGateway */
    private function fakeGateway()
    {
        $gateway = Mockery::mock(ShippingGateway::class);
        $this->app->instance(ShippingGateway::class, $gateway);

        return $gateway;
    }

    private function carrierOptions(): array
    {
        return [
            new DeliveryOption(id: 11, carrier: 'Naqel', price: 23.0, estimatedDelivery: '1-2 days'),
            new DeliveryOption(id: 22, carrier: 'SMSA', price: 19.5, estimatedDelivery: '2-3 days'),
        ];
    }

    private function shipment(string $tracking = 'TRK-1', string $carrier = 'SMSA'): NormalizedShipment
    {
        return new NormalizedShipment(trackingNumber: $tracking, carrier: $carrier, labelUrl: null, otoId: 555);
    }

    public function test_quotes_are_returned_cheapest_first(): void
    {
        $order = $this->makeOrder();
        $gateway = $this->fakeGateway();
        $gateway->shouldReceive('pushOrder')->andReturn(555);
        $gateway->shouldReceive('getDeliveryOptions')->andReturn($this->carrierOptions());

        $body = $this->actingAs($this->admin())
            ->getJson("/admin/orders/{$order->order_number}/shipping-quotes")
            ->assertOk()
            ->json();

        $this->assertNull($body['error']);
        $this->assertSame([22, 11], array_column($body['options'], 'id'), 'options must be price-sorted');
        $this->assertSame('SMSA', $body['options'][0]['carrier']);
    }

    /**
     * A quote crosses the network, so it can fail for reasons unrelated to this
     * order. The admin must SEE why rather than get a dead spinner, so the
     * endpoint answers 200 with the error as data.
     */
    public function test_a_failing_quote_answers_200_with_the_provider_message(): void
    {
        $order = $this->makeOrder();
        $gateway = $this->fakeGateway();
        $gateway->shouldReceive('pushOrder')->andThrow(new \RuntimeException('OTO credentials rejected'));

        $body = $this->actingAs($this->admin())
            ->getJson("/admin/orders/{$order->order_number}/shipping-quotes")
            ->assertOk()
            ->json();

        $this->assertSame([], $body['options']);
        $this->assertSame('OTO credentials rejected', $body['error']);
    }

    /** No carrier chosen ⇒ the cheapest option id reaches the provider. */
    public function test_shipping_without_a_choice_uses_the_cheapest_carrier(): void
    {
        $order = $this->makeOrder();
        $gateway = $this->fakeGateway();
        $gateway->shouldReceive('pushOrder')->andReturn(555);
        $gateway->shouldReceive('getDeliveryOptions')->andReturn($this->carrierOptions());
        $gateway->shouldReceive('createShipment')
            ->once()
            ->with(Mockery::type(Order::class), 22) // SMSA @ 19.50, the cheaper one
            ->andReturn($this->shipment());

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->order_number}/ship")
            ->assertSessionHas('success');

        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
    }

    /** An explicit choice must override the cheapest, even when it costs more. */
    public function test_an_explicitly_chosen_carrier_is_used(): void
    {
        $order = $this->makeOrder();
        $gateway = $this->fakeGateway();
        $gateway->shouldReceive('pushOrder')->andReturn(555);
        $gateway->shouldNotReceive('getDeliveryOptions'); // no need to quote when told which
        $gateway->shouldReceive('createShipment')
            ->once()
            ->with(Mockery::type(Order::class), 11) // Naqel @ 23.00, the dearer one
            ->andReturn($this->shipment(carrier: 'Naqel'));

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->order_number}/ship", ['delivery_option_id' => 11])
            ->assertSessionHas('success');

        $this->assertSame('Naqel', $order->refresh()->carrier);
    }

    public function test_a_non_numeric_carrier_choice_is_rejected(): void
    {
        $order = $this->makeOrder();
        $this->fakeGateway()->shouldNotReceive('createShipment');

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->order_number}/ship", ['delivery_option_id' => 'not-an-id'])
            ->assertSessionHasErrors('delivery_option_id');
    }

    /**
     * 🔴 The regression that motivated this work: the Cancel button was gated to
     * `status === Confirmed`, the EXACT complement of what cancelByCustomer
     * accepts, so it appeared only where it was guaranteed to fail.
     */
    public function test_the_order_cancel_button_is_offered_only_where_it_can_succeed(): void
    {
        $admin = $this->admin();

        $pending = $this->makeOrder([
            'order_number' => 'RTB-GATE-1',
            'status' => OrderStatus::AwaitingConfirmation,
        ]);
        $confirmed = $this->makeOrder(['order_number' => 'RTB-GATE-2', 'status' => OrderStatus::Confirmed]);

        $can = fn (Order $o) => $this->actingAs($admin)
            ->get("/admin/orders/{$o->order_number}")
            ->inertiaPage()['props']['can'];

        $this->assertTrue($can($pending)['cancel'], 'pre-confirm orders must be cancellable');
        $this->assertFalse($can($confirmed)['cancel'], 'a confirmed order cannot be cancelled — do not offer it');
    }

    /** And the offer must be honoured, not just shown. */
    public function test_cancelling_a_pre_confirm_order_succeeds(): void
    {
        $order = $this->makeOrder(['status' => OrderStatus::AwaitingConfirmation, 'payment_status' => PaymentStatus::Pending]);

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->order_number}/cancel")
            ->assertSessionHas('success');

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    /**
     * Recalling a shipment returns the order to confirmed, clears the tracking
     * details, and moves NO money — the order is still owed to the customer.
     */
    public function test_cancelling_a_shipment_returns_the_order_to_confirmed_without_refunding(): void
    {
        $order = $this->makeOrder([
            'status' => OrderStatus::Shipped,
            'tracking_number' => 'TRK-1',
            'carrier' => 'SMSA',
            'oto_id' => 555,
        ]);

        $this->fakeGateway()->shouldReceive('cancelShipment')->once()->andReturn(true);

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->order_number}/cancel-shipment")
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNull($order->tracking_number);
        $this->assertNull($order->carrier);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status, 'recalling a parcel must not refund');
        // Cast: oto_id has no model cast, so the driver decides int vs string.
        $this->assertSame(555, (int) $order->oto_id, 'the OTO order is kept so a re-ship reuses it');
    }

    /** The whole point of recalling: ship it again, with a different carrier. */
    public function test_an_order_can_be_shipped_again_after_its_shipment_is_cancelled(): void
    {
        $order = $this->makeOrder([
            'status' => OrderStatus::Shipped,
            'tracking_number' => 'TRK-1',
            'carrier' => 'SMSA',
            'oto_id' => 555,
        ]);
        $admin = $this->admin();

        $gateway = $this->fakeGateway();
        $gateway->shouldReceive('cancelShipment')->once()->andReturn(true);
        $gateway->shouldReceive('createShipment')
            ->once()
            ->with(Mockery::type(Order::class), 11)
            ->andReturn($this->shipment('TRK-2', 'Naqel'));

        $this->actingAs($admin)->post("/admin/orders/{$order->order_number}/cancel-shipment");

        // The Ship button must come back, and Cancel-shipment must go away.
        $can = $this->actingAs($admin)->get("/admin/orders/{$order->order_number}")->inertiaPage()['props']['can'];
        $this->assertTrue($can['ship']);
        $this->assertFalse($can['cancelShipment']);

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->order_number}/ship", ['delivery_option_id' => 11])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('TRK-2', $order->tracking_number);
        $this->assertSame('Naqel', $order->carrier);
    }

    /** Guards the idempotency rule: never two parcels for one order. */
    public function test_a_shipped_order_cannot_be_shipped_twice(): void
    {
        $order = $this->makeOrder(['status' => OrderStatus::Shipped, 'tracking_number' => 'TRK-1']);
        $this->fakeGateway()->shouldNotReceive('createShipment');

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->order_number}/ship")
            ->assertSessionHas('error');
    }

    public function test_cancel_shipment_is_not_offered_once_delivered(): void
    {
        $order = $this->makeOrder([
            'status' => OrderStatus::Delivered,
            'tracking_number' => 'TRK-1',
            'delivered_at' => now(),
        ]);

        $can = $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->order_number}")
            ->inertiaPage()['props']['can'];

        $this->assertFalse($can['cancelShipment']);
    }

    /** Editors without orders.manage must not reach any of it. */
    public function test_shipping_actions_require_the_manage_permission(): void
    {
        $order = $this->makeOrder();
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->forceFill(['permissions' => ['orders' => ['view' => true, 'manage' => false, 'export' => false]]])->save();

        $this->actingAs($editor->fresh())->get("/admin/orders/{$order->order_number}/shipping-quotes")->assertForbidden();
        $this->actingAs($editor->fresh())->post("/admin/orders/{$order->order_number}/ship")->assertForbidden();
        $this->actingAs($editor->fresh())->post("/admin/orders/{$order->order_number}/cancel-shipment")->assertForbidden();
    }
}
