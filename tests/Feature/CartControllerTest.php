<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartControllerTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'التمور', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'slug' => 'sukkari-' . uniqid(),
            'price' => 50,
            'sku' => 'SK-' . uniqid(),
            'stock' => 10,
            'is_active' => true,
        ]);
    }

    public function test_add_to_cart_creates_an_item(): void
    {
        $product = $this->product();

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_adding_same_product_increments_quantity(): void
    {
        $product = $this->product();

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 3]);
    }

    public function test_cart_page_renders(): void
    {
        $this->get('/cart')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('shop/cart')->has('items'),
        );
    }

    public function test_remove_item(): void
    {
        $product = $this->product();
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $item = CartItem::firstOrFail();

        $this->delete("/cart/items/{$item->id}")->assertRedirect();

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }
}
