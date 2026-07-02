<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderConfirmationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OrderConfirmationService
    {
        return app(OrderConfirmationService::class);
    }

    private function makeProduct(int $stock = 100): Product
    {
        $category = Category::create(['name_ar' => 'تمور', 'slug' => 'dates-' . uniqid()]);

        return Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'slug' => 'p-' . uniqid(),
            'price' => 50,
            'sku' => 'SK-' . uniqid(),
            'stock' => $stock,
        ]);
    }

    private function makeOrder(Product $product, array $overrides = [], int $qty = 3): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'RTB-' . uniqid(),
            'customer_name' => 'Zaid',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'subtotal' => 150,
            'total' => 175,
        ], $overrides));

        $order->items()->create([
            'product_id' => $product->id,
            'product_name_ar' => $product->name_ar,
            'unit_price' => 50,
            'quantity' => $qty,
            'line_total' => 50 * $qty,
        ]);

        return $order;
    }

    public function test_confirm_deducts_stock_and_marks_confirmed(): void
    {
        $product = $this->makeProduct(100);
        $order = $this->makeOrder($product, [], 3);

        $this->service()->confirm($order);

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNotNull($order->confirmed_at);
        $this->assertSame(97, $product->fresh()->stock);
        $this->assertDatabaseHas('order_activities', [
            'order_id' => $order->id, 'type' => 'status_change', 'to_status' => 'confirmed',
        ]);
    }

    public function test_confirm_rejects_orders_not_awaiting_confirmation(): void
    {
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, ['status' => OrderStatus::Confirmed]);

        $this->expectException(\RuntimeException::class);
        $this->service()->confirm($order);
    }

    public function test_mark_unavailable_logs_demand_and_keeps_stock(): void
    {
        $product = $this->makeProduct(100);
        $order = $this->makeOrder($product, [], 3);

        $this->service()->markUnavailable($order, note: 'out of stock');

        $order->refresh();
        $this->assertSame(OrderStatus::Unavailable, $order->status);
        $this->assertSame(100, $product->fresh()->stock); // never deducted
        $this->assertDatabaseHas('demand_events', [
            'order_id' => $order->id, 'product_id' => $product->id, 'action' => 'unavailable',
        ]);
    }

    public function test_confirm_counts_the_purchase_toward_loyalty(): void
    {
        $user = \App\Models\User::factory()->create();
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, ['user_id' => $user->id], 1);

        $this->service()->confirm($order);

        $this->assertSame(1, $user->fresh()->confirmed_purchases_count);
    }

    public function test_customer_can_cancel_before_confirmation(): void
    {
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, [
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $this->service()->cancelByCustomer($order);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNotNull($order->cancelled_at);
    }
}
