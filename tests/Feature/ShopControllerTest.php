<?php

namespace Tests\Feature;

use App\Models\Category;
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
}
