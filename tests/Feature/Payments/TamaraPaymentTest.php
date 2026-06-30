<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payments\Tamara\TamaraClient;
use App\Services\Payments\Tamara\TamaraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TamaraPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'RTB-TMR-1',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'tamara',
            'gateway_reference' => 'tamara_1',
            'subtotal' => 100,
            'total' => 125,
        ], $overrides));
    }

    /** TamaraService backed by a fake client returning a canned remote status. */
    private function service(string $remoteStatus = 'approved'): TamaraService
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

            public function capture(string $orderId, array $payload): array
            {
                return ['capture_id' => 'cap_1'];
            }

            public function cancel(string $orderId, array $payload): array
            {
                return [];
            }
        };
        $client->remoteStatus = $remoteStatus;

        return new TamaraService($client);
    }

    public function test_confirm_authorizes_and_awaits_confirmation_without_capturing(): void
    {
        $order = $this->makeOrder();

        $this->service('approved')->confirm('tamara_1');

        $order->refresh();
        $this->assertSame(PaymentStatus::Authorized, $order->payment_status);
        $this->assertSame(OrderStatus::AwaitingConfirmation, $order->status);
        $this->assertNull($order->paid_at); // money is HELD, not taken
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'type' => 'authorization', 'status' => 'authorized',
        ]);
    }

    public function test_capture_takes_the_money_and_marks_paid(): void
    {
        $order = $this->makeOrder([
            'payment_status' => PaymentStatus::Authorized,
            'status' => OrderStatus::AwaitingConfirmation,
        ]);

        $this->service('authorised')->capture($order);

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'type' => 'capture', 'status' => 'succeeded',
        ]);
    }

    public function test_declined_order_is_failed(): void
    {
        $order = $this->makeOrder();

        $this->service('declined')->confirm('tamara_1');

        $order->refresh();
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
    }
}
