<?php

namespace Tests\Feature\Seo;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Product;
use App\Ssr\TimeoutHttpGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Ssr\Gateway;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'is_active' => true]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name_ar' => 'تمر سكري',
            'slug' => 'sukkari',
            'sku' => 'SKU-' . fake()->unique()->numerify('####'),
            'price' => 50,
            'stock' => 10,
            'is_active' => true,
        ], $overrides));
    }

    public function test_sitemap_lists_active_products_and_published_pages_only(): void
    {
        $this->makeProduct();
        $this->makeProduct(['slug' => 'hidden', 'is_active' => false]);
        ContentPage::create(['slug' => 'about', 'title_ar' => 'من نحن', 'body_ar' => 'نص', 'is_published' => true]);
        ContentPage::create(['slug' => 'draft', 'title_ar' => 'مسودة', 'body_ar' => 'نص', 'is_published' => false]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = $response->getContent();
        $this->assertStringContainsString('/products/sukkari', $xml);
        $this->assertStringContainsString('/pages/about', $xml);
        $this->assertStringNotContainsString('/products/hidden', $xml);
        $this->assertStringNotContainsString('/pages/draft', $xml);
    }

    public function test_robots_disallows_private_areas_and_points_to_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Sitemap: ' . route('seo.sitemap'), $body);
    }

    public function test_ssr_gateway_binding_resolves_to_timeout_gateway(): void
    {
        $this->assertInstanceOf(TimeoutHttpGateway::class, app(Gateway::class));
    }

    public function test_product_payload_carries_absolute_url_for_json_ld(): void
    {
        $product = $this->makeProduct();

        $url = $this->get("/products/{$product->slug}")->inertiaPage()['props']['product']['url'];

        $this->assertSame(route('shop.product', $product->slug), $url);
    }
}
