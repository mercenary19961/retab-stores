<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The dashboard's Inventory health figures link to the products list filtered to
 * what they count. The whole point is that the number clicked and the rows that
 * open agree, so that is what these tests pin.
 */
class ProductStockFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function product(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'name_en' => 'Dates', 'is_active' => true]);

        $product = Product::create(array_merge([
            'category_id' => $category->id,
            'name_ar' => 'منتج',
            'slug' => 'p-'.uniqid(),
            'price' => 50,
            'sku' => 'T-'.uniqid(),
            'stock' => 100,
            'is_active' => true,
        ], $overrides));
        // An image, or the publish guard would hide it on its next save.
        $product->images()->create(['path' => "products/{$product->id}/a.webp", 'sort_order' => 1, 'is_primary' => true]);

        return $product;
    }

    private function seedStock(): void
    {
        $this->product(['name_ar' => 'نفد', 'stock' => 0]);
        $this->product(['name_ar' => 'قليل', 'stock' => 3]);
        $this->product(['name_ar' => 'حد خاص', 'stock' => 15, 'low_stock_threshold' => 20]);
        $this->product(['name_ar' => 'متوفر', 'stock' => 100]);
        // Hidden products are never counted: they are not on sale to run out.
        $this->product(['name_ar' => 'مخفي', 'stock' => 0, 'is_active' => false]);
    }

    public function test_each_stock_filter_lists_exactly_what_the_dashboard_counts(): void
    {
        $this->seedStock();
        $this->actingAs($this->admin());

        $inventory = $this->get('/admin/dashboard')->assertOk()->viewData('page')['props']['inventory'];

        $this->assertSame(1, $inventory['outOfStock']);
        // Zero stock is the lowest stock there is, plus the one under the default
        // line and the one under its own threshold.
        $this->assertSame(3, $inventory['lowStock']);

        foreach (['out_of_stock' => $inventory['outOfStock'], 'low_stock' => $inventory['lowStock'], 'active' => $inventory['activeProducts']] as $status => $count) {
            $this->get("/admin/products?status={$status}")->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', $status)
                ->where('products.total', $count));
        }
    }

    public function test_the_out_of_stock_list_holds_only_live_empty_products(): void
    {
        $this->seedStock();
        $this->actingAs($this->admin());

        $this->get('/admin/products?status=out_of_stock')->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name_ar', 'نفد'));
    }
}
