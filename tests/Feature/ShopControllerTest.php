<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\ClientReview;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

    public function test_shop_lists_active_products(): void
    {
        $this->makeProduct();

        $this->get('/shop')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('shop/catalogue')->has('products.data', 1)->has('categories', 1),
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

    public function test_shop_filters_by_category(): void
    {
        $this->makeProduct(); // seeded into the 'dates' category

        $this->get('/shop?category=dates')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('shop/catalogue')->has('products.data', 1)->where('activeCategory', 'dates'),
        );
    }

    public function test_catalogue_search_matches_the_product_name(): void
    {
        $this->makeProduct(['slug' => 'sukkari', 'name_ar' => 'سكري']);
        $this->makeProduct(['slug' => 'khalas', 'name_ar' => 'خلاص', 'name_en' => 'Khalas']);

        $this->get('/shop?q=Khalas')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('products.data', 1)
                ->where('products.data.0.slug', 'khalas')
                ->where('filters.q', 'Khalas'),
        );
    }

    public function test_catalogue_offers_filter_returns_only_on_sale_products(): void
    {
        $this->makeProduct(['slug' => 'regular', 'price' => 50]);
        $this->makeProduct(['slug' => 'discounted', 'price' => 50, 'sale_price' => 40]);

        $this->get('/shop?on_sale=1')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('products.data', 1)
                ->where('products.data.0.slug', 'discounted')
                ->where('filters.on_sale', true),
        );
    }

    public function test_catalogue_sorts_by_price_ascending(): void
    {
        $this->makeProduct(['slug' => 'mid', 'price' => 20]);
        $this->makeProduct(['slug' => 'cheap', 'price' => 10]);
        $this->makeProduct(['slug' => 'dear', 'price' => 30]);

        $this->get('/shop?sort=price_asc')->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('products.data.0.slug', 'cheap')
                ->where('products.data.2.slug', 'dear'),
        );
    }

    public function test_filtering_is_a_partial_reload_that_skips_the_category_list(): void
    {
        $this->makeProduct();

        // Match the asset version so Inertia serves the partial (not a 409 reload).
        $version = app(HandleInertiaRequests::class)->version(Request::create('/shop'));

        // A filter interaction asks only for products/filters/activeCategory; the
        // deferred `categories` closure must NOT be evaluated or sent. A partial
        // response is raw JSON (not the HTML page), so assert on it directly.
        $response = $this->get('/shop', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'shop/catalogue',
            'X-Inertia-Partial-Data' => 'products,filters,activeCategory',
        ])->assertOk();

        $props = $response->json('props');
        $this->assertArrayHasKey('products', $props);
        $this->assertArrayHasKey('filters', $props);
        $this->assertArrayNotHasKey('categories', $props); // deferred → skipped
        $this->assertCount(1, $props['products']['data']);
    }

    public function test_catalogue_paginates_twelve_per_page(): void
    {
        foreach (range(1, 13) as $i) {
            $this->makeProduct(['slug' => "p-{$i}", 'sku' => "SKU-{$i}"]);
        }

        $this->get('/shop')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('products.data', 12)->where('products.total', 13),
        );

        $this->get('/shop?page=2')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('products.data', 1),
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

    public function test_home_includes_new_arrivals_newest_first(): void
    {
        $this->makeProduct(['slug' => 'older', 'sku' => 'OLD-1']);
        $newest = $this->makeProduct(['slug' => 'newer', 'sku' => 'NEW-1']);
        // Force a later timestamp so ordering is deterministic on fast machines.
        $newest->forceFill(['created_at' => now()->addMinute()])->save();

        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('newArrivals', 2)
                ->where('newArrivals.0.slug', 'newer'),
        );
    }

    public function test_home_includes_only_active_client_reviews(): void
    {
        $this->makeProduct();
        ClientReview::create(['author_name' => 'Active One', 'body' => 'Great dates.', 'rating' => 5, 'is_active' => true]);
        ClientReview::create(['author_name' => 'Hidden One', 'body' => 'Not shown.', 'rating' => 4, 'is_active' => false]);

        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('reviews', 1)
                ->where('reviews.0.author_name', 'Active One'),
        );
    }

    public function test_home_includes_only_categories_with_an_image(): void
    {
        // The catalogue category from makeProduct() has no image → excluded.
        $this->makeProduct();
        Category::create([
            'name_ar' => 'خلاص',
            'slug' => 'khalas',
            'image' => '/images/categories/khalas.webp',
            'is_active' => true,
        ]);

        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('featuredCategories', 1)
                ->where('featuredCategories.0.slug', 'khalas')
                ->where('featuredCategories.0.image', '/images/categories/khalas.webp'),
        );
    }
}
