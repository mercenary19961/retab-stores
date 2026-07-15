<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function product(array $overrides = []): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'search-cat'], ['name_ar' => 'تمور']);

        return Product::create(array_merge([
            'category_id' => $cat->id,
            'name_ar' => 'سكري',
            'slug' => 'p-' . uniqid(),
            'price' => 50,
            'sku' => 'SK-' . uniqid(),
            'stock' => 10,
        ], $overrides));
    }

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'RTB-' . uniqid(),
            'customer_name' => 'زيد',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA'],
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 100,
            'total' => 100,
        ], $overrides));
    }

    public function test_short_query_returns_no_groups(): void
    {
        $this->product(['name_ar' => 'خلاص']);

        $this->actingAs($this->staff())
            ->getJson('/admin/search?q=a')
            ->assertOk()
            ->assertExactJson(['groups' => []]);
    }

    public function test_finds_product_by_sku(): void
    {
        $this->product(['name_ar' => 'تمر خلاص فاخر', 'sku' => 'KHL-1KG']);

        $groups = $this->actingAs($this->staff())
            ->getJson('/admin/search?q=KHL-1KG')
            ->assertOk()
            ->json('groups');

        $this->assertSame('products', $groups[0]['type']);
        $this->assertSame('تمر خلاص فاخر', $groups[0]['items'][0]['label']);
    }

    public function test_ranks_exact_match_above_prefix(): void
    {
        $this->product(['name_ar' => 'المطابق', 'sku' => 'SUK']);      // exact hit on "suk"
        $this->product(['name_ar' => 'الأطول', 'sku' => 'SUK-500']);   // prefix hit only

        $products = collect(
            $this->actingAs($this->staff())->getJson('/admin/search?q=suk')->json('groups')
        )->firstWhere('type', 'products');

        $this->assertSame('المطابق', $products['items'][0]['label']);
    }

    public function test_searches_multiple_entities_at_once(): void
    {
        $this->product(['name_ar' => 'ذهبي']);
        User::factory()->create(['role' => 'customer', 'name' => 'ذهبي']);

        $types = collect(
            $this->actingAs($this->staff())->getJson('/admin/search?q=ذهبي')->json('groups')
        )->pluck('type');

        $this->assertTrue($types->contains('products'));
        $this->assertTrue($types->contains('customers'));
    }

    public function test_finds_order_by_number(): void
    {
        $this->order(['order_number' => 'RTB-2026-777']);

        $groups = $this->actingAs($this->staff())
            ->getJson('/admin/search?q=RTB-2026-777')
            ->json('groups');

        $this->assertSame('orders', $groups[0]['type']);
        $this->assertSame('RTB-2026-777', $groups[0]['items'][0]['label']);
    }

    public function test_customers_cannot_search(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->getJson('/admin/search?q=anything')->assertForbidden();
    }
}
