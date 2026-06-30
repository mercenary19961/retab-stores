<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function makeOrder(int $stock = 100, array $overrides = [], int $qty = 3): Order
    {
        $category = Category::create(['name_ar' => 'تمور', 'slug' => 'dates-' . uniqid()]);
        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'slug' => 'p-' . uniqid(),
            'price' => 50,
            'sku' => 'SK-' . uniqid(),
            'stock' => $stock,
        ]);

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
            'sku' => $product->sku,
            'unit_price' => 50,
            'quantity' => $qty,
            'line_total' => 50 * $qty,
        ]);

        return $order;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/orders')->assertRedirect('/login');
    }

    public function test_customers_are_forbidden(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get('/admin/orders')->assertForbidden();
    }

    public function test_staff_can_list_orders(): void
    {
        $this->makeOrder();

        $this->actingAs($this->staff())
            ->get('/admin/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/index')
                ->has('orders.data', 1));
    }

    public function test_editor_can_confirm_order_and_stock_is_deducted(): void
    {
        $order = $this->makeOrder(100, [], 3);

        $this->actingAs($this->staff('editor'))
            ->post("/admin/orders/{$order->order_number}/confirm")
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(97, $order->items->first()->product->fresh()->stock);
    }

    public function test_mark_unavailable_records_demand_event(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->staff())
            ->post("/admin/orders/{$order->order_number}/unavailable", ['note' => 'out of stock'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Unavailable, $order->fresh()->status);
        $this->assertDatabaseHas('demand_events', ['order_id' => $order->id, 'action' => 'unavailable']);
    }

    public function test_confirming_a_non_awaiting_order_flashes_an_error(): void
    {
        $order = $this->makeOrder(100, ['status' => OrderStatus::Confirmed]);

        $this->actingAs($this->staff())
            ->post("/admin/orders/{$order->order_number}/confirm")
            ->assertSessionHas('error');
    }
}
