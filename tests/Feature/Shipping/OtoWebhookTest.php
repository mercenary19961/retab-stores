<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * OTO's dashboard sends its "Authorization Key" field verbatim in the
     * Authorization header, which is its documented mechanism — so the header
     * must authenticate on its own, with no token in the URL.
     */
    #[DataProvider('authorizationHeaderProvider')]
    public function test_the_authorization_header_authenticates_a_webhook(string $header): void
    {
        config(['services.oto.webhook_secret' => 'secret']);
        $order = $this->makeOrder();

        $this->postJson('/webhooks/oto', [
            'orderId' => $order->order_number,
            'status' => 'delivered',
        ], ['Authorization' => $header])->assertOk();

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
    }

    /** The dashboard doesn't say whether it prefixes the key, so tolerate both. */
    public static function authorizationHeaderProvider(): array
    {
        return [
            'raw key' => ['secret'],
            'bearer prefixed' => ['Bearer secret'],
        ];
    }

    public function test_a_wrong_authorization_header_is_still_rejected(): void
    {
        config(['services.oto.webhook_secret' => 'secret']);
        $order = $this->makeOrder();

        $this->postJson('/webhooks/oto', [
            'orderId' => $order->order_number,
            'status' => 'delivered',
        ], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
    }

    /**
     * 🔴 OTO's documented payloads are camelCase (`shipmentProcessing`), but the
     * status map only spelled `out_for_delivery` with underscores, so a real
     * `outForDelivery` callback was silently ignored and the order stayed put.
     */
    #[DataProvider('shippedStatusProvider')]
    public function test_shipping_statuses_map_regardless_of_the_provider_casing(string $providerStatus): void
    {
        config(['services.oto.webhook_secret' => 'secret']);
        $order = $this->makeOrder(['status' => OrderStatus::Confirmed]);

        $this->postJson('/webhooks/oto?token=secret', [
            'orderId' => $order->order_number,
            'status' => $providerStatus,
        ])->assertOk();

        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status, "'{$providerStatus}' did not map to shipped");
    }

    public static function shippedStatusProvider(): array
    {
        return [
            'camelCase' => ['outForDelivery'],
            'snake_case' => ['out_for_delivery'],
            'picked up' => ['pickedUp'],
            'in transit' => ['inTransit'],
            'plain' => ['shipped'],
        ];
    }

    /** Intermediate states we deliberately ignore must leave the order alone. */
    public function test_an_unmapped_status_is_ignored(): void
    {
        config(['services.oto.webhook_secret' => 'secret']);
        $order = $this->makeOrder(['status' => OrderStatus::Confirmed]);

        $this->postJson('/webhooks/oto?token=secret', [
            'orderId' => $order->order_number,
            'status' => 'shipmentProcessing',
        ])->assertOk();

        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
    }
}
