<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtoWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'RTB-TEST-1',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::Shipped,
            'subtotal' => 100,
            'total' => 125,
        ], $overrides));
    }

    public function test_valid_webhook_marks_order_delivered_and_sets_delivered_at(): void
    {
        config(['services.oto.webhook_secret' => 'secret']);
        $order = $this->makeOrder();

        $response = $this->postJson('/webhooks/oto?token=secret', [
            'orderId' => $order->order_number,
            'status' => 'delivered',
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertNotNull($order->delivered_at); // starts the 3-day return window
    }

    public function test_webhook_with_bad_token_is_rejected(): void
    {
        config(['services.oto.webhook_secret' => 'secret']);
        $order = $this->makeOrder();

        $response = $this->postJson('/webhooks/oto?token=wrong', [
            'orderId' => $order->order_number,
            'status' => 'delivered',
        ]);

        $response->assertUnauthorized();
        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
    }
}
