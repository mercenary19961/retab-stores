<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'dates'],
            ['name_ar' => 'التمور', 'is_active' => true],
        );

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'slug' => 'sukkari',
            'price' => 50,
            'sku' => 'SK-' . uniqid(),
            'stock' => 10,
            'is_active' => true,
        ], $overrides));
    }

    public function test_home_lists_active_products(): void
    {
        $this->makeProduct();

        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('shop/index')->has('products', 1)->has('categories', 1),
        );
    }

    public function test_product_page_renders(): void
    {
        $this->makeProduct(['slug' => 'sukkari-1kg']);

        $this->get('/products/sukkari-1kg')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('shop/product')->where('product.slug', 'sukkari-1kg'),
        );
    }

    public function test_inactive_product_is_not_found(): void
    {
        $this->makeProduct(['slug' => 'hidden', 'is_active' => false]);

        $this->get('/products/hidden')->assertNotFound();
    }

    public function test_home_includes_best_sellers(): void
    {
        // With no sales yet, the strip still populates (featured/newest fallback).
        $this->makeProduct();

        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('bestSellers', 1),
        );
    }

    public function test_best_sellers_omitted_on_filtered_category_view(): void
    {
        $this->makeProduct();

        $this->get('/?category=dates')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('bestSellers', 0),
        );
    }

    public function test_best_sellers_rank_by_units_sold(): void
    {
        $this->makeProduct(['slug' => 'khalas', 'name_ar' => 'خلاص']);
        $popular = $this->makeProduct(['slug' => 'ajwa', 'name_ar' => 'عجوة']);

        $order = Order::create([
            'order_number' => 'R-1001',
            'customer_name' => 'Test',
            'customer_phone' => '0500000000',
            'shipping_address' => ['city' => 'Riyadh'],
            'status' => OrderStatus::Confirmed,
            'subtotal' => 150,
            'total' => 150,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $popular->id,
            'product_name_ar' => $popular->name_ar,
            'unit_price' => 50,
            'quantity' => 3,
            'line_total' => 150,
        ]);

        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('bestSellers.0.slug', 'ajwa'),
        );
    }

    public function test_home_includes_only_categories_with_an_image(): void
    {
        // The catalogue category from makeProduct() has no image → excluded.
        $this->makeProduct();
        Category::create([
            'name_ar' => 'خلاص',
            'slug' => 'khalas',
            'image' => '/images/categories/khalas.png',
            'is_active' => true,
        ]);

        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('featuredCategories', 1)
                ->where('featuredCategories.0.slug', 'khalas')
                ->where('featuredCategories.0.image', '/images/categories/khalas.png'),
        );
    }
}
