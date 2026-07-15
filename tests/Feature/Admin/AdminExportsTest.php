<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the shared TableExport across orders / returns / customers / inventory:
 * CSV content, filter passthrough, the xlsx + json formats, and the staff gate.
 * (Product export is covered in AdminProductControllerTest.)
 */
class AdminExportsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'RTB-' . uniqid(),
            'customer_name' => 'زيد العميل',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 150,
            'total' => 175,
        ], $overrides));
    }

    private function product(string $name, int $stock): Product
    {
        $category = Category::firstOrCreate(['slug' => 'exp-cat'], ['name_ar' => 'تمور']);

        return Product::create([
            'category_id' => $category->id,
            'name_ar' => $name,
            'slug' => 'p-' . uniqid(),
            'price' => 10,
            'sku' => 'SK-' . uniqid(),
            'stock' => $stock,
        ]);
    }

    public function test_orders_export_csv_and_status_filter(): void
    {
        $this->order(['customer_name' => 'مؤكد', 'status' => OrderStatus::Confirmed]);
        $this->order(['customer_name' => 'قيد الانتظار', 'status' => OrderStatus::AwaitingConfirmation]);

        $body = $this->actingAs($this->staff())
            ->get('/admin/orders/export?format=csv&status=confirmed')
            ->streamedContent();

        $this->assertStringContainsString('order_number', $body); // header
        $this->assertStringContainsString('مؤكد', $body);
        $this->assertStringNotContainsString('قيد الانتظار', $body);
    }

    public function test_returns_export_csv(): void
    {
        $order = $this->order();
        OrderReturn::create(['order_id' => $order->id, 'status' => ReturnStatus::Requested, 'reason' => 'تمر تالف']);

        $body = $this->actingAs($this->staff())
            ->get('/admin/returns/export?format=csv')
            ->streamedContent();

        $this->assertStringContainsString('order_number', $body);
        $this->assertStringContainsString('تمر تالف', $body);
    }

    public function test_customers_export_excludes_staff(): void
    {
        User::factory()->create(['role' => 'customer', 'name' => 'عميل حقيقي']);
        User::factory()->create(['role' => 'editor', 'name' => 'موظف داخلي']);

        $body = $this->actingAs($this->staff())
            ->get('/admin/customers/export?format=csv')
            ->streamedContent();

        $this->assertStringContainsString('عميل حقيقي', $body);
        $this->assertStringNotContainsString('موظف داخلي', $body);
    }

    public function test_inventory_export_and_low_stock_filter(): void
    {
        $this->product('مخزون عالٍ', 500);
        $this->product('مخزون منخفض', 1);

        $staff = $this->staff();

        $all = $this->actingAs($staff)->get('/admin/stock-import/export?format=csv')->streamedContent();
        $this->assertStringContainsString('مخزون عالٍ', $all);
        $this->assertStringContainsString('مخزون منخفض', $all);

        $low = $this->actingAs($staff)->get('/admin/stock-import/export?format=csv&low=1')->streamedContent();
        $this->assertStringContainsString('مخزون منخفض', $low);
        $this->assertStringNotContainsString('مخزون عالٍ', $low);
    }

    public function test_xlsx_and_json_formats(): void
    {
        $this->order();
        $staff = $this->staff();

        $xlsx = $this->actingAs($staff)->get('/admin/orders/export?format=xlsx');
        $xlsx->assertOk();
        $this->assertStringContainsString('.xlsx', $xlsx->headers->get('content-disposition'));

        $json = $this->actingAs($staff)->get('/admin/orders/export?format=json')->streamedContent();
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('order_number', $data[0]);
    }

    public function test_customers_cannot_export_any_section(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        foreach (['orders', 'returns', 'customers', 'stock-import'] as $section) {
            $this->actingAs($customer)->get("/admin/{$section}/export?format=csv")->assertForbidden();
        }
    }
}
