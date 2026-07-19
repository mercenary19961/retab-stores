<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\Category;
use App\Models\DemandEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(float $total, OrderStatus $status): Order
    {
        return Order::create([
            'order_number' => 'RTB-' . uniqid(),
            'customer_name' => 'زيد',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA'],
            'status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'subtotal' => $total,
            'total' => $total,
        ]);
    }

    public function test_dashboard_computes_kpis_tasks_inventory_and_insights(): void
    {
        $cat = Category::create(['name_ar' => 'تمور', 'slug' => 'c-' . uniqid()]);
        $mk = fn (string $name, int $stock) => Product::create([
            'category_id' => $cat->id, 'name_ar' => $name, 'slug' => 'p-' . uniqid(),
            'price' => 20, 'sku' => 'SK-' . uniqid(), 'stock' => $stock, 'is_active' => true,
        ]);

        $normal = $mk('عادي', 100);
        $mk('منخفض', 2);   // low stock
        $out = $mk('نافد', 0); // out of stock (also counts as low)

        // Paid revenue: 100 + 50 + 60 = 210 across 3 paid orders in the last 30 days.
        $o1 = $this->paidOrder(100, OrderStatus::AwaitingConfirmation);
        $this->paidOrder(50, OrderStatus::Delivered);
        $this->paidOrder(60, OrderStatus::Confirmed); // ready to ship

        OrderItem::create([
            'order_id' => $o1->id, 'product_id' => $normal->id, 'product_name_ar' => 'عادي',
            'unit_price' => 20, 'quantity' => 3, 'line_total' => 60,
        ]);

        // A bank transfer awaiting manual verification (pending, not paid).
        Order::create([
            'order_number' => 'RTB-' . uniqid(), 'customer_name' => 'خالد', 'customer_phone' => '+966500000001',
            'shipping_address' => ['country' => 'SA'], 'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending, 'payment_method' => PaymentMethod::BankTransfer,
            'subtotal' => 40, 'total' => 40,
        ]);

        OrderReturn::create(['order_id' => $o1->id, 'status' => ReturnStatus::Requested, 'reason' => 'تالف']);
        DemandEvent::create(['product_id' => $out->id, 'action' => 'apologized']);

        User::factory()->create(['role' => 'customer', 'confirmed_purchases_count' => 4]); // one from a reward
        User::factory()->create(['role' => 'customer', 'whatsapp_opt_in' => true]);

        $task = function (Collection $tasks, string $key): int {
            $row = collect($tasks)->firstWhere('key', $key);

            return is_array($row) ? (int) $row['count'] : 0;
        };

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kpis.revenue30', 210)
                ->where('kpis.orders30', 3)
                ->where('inventory.outOfStock', 1)
                ->where('inventory.lowStock', 2)
                ->where('customers.nearReward', 1)
                ->where('customers.whatsappAudience', 1)
                ->where('insights.topProducts.0.product_id', $normal->id)
                ->where('insights.topProducts.0.qty', 3)
                ->where('insights.demand.0.product_id', $out->id)
                ->where('tasks', fn (Collection $tasks) => $task($tasks, 'bankTransfers') === 1
                    && $task($tasks, 'returnsToReview') === 1
                    && $task($tasks, 'readyToShip') === 1
                    && $task($tasks, 'awaitingConfirmation') === 1)
            );
    }
}
