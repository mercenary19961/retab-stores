<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewWishlistControllerTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'slug' => 'sukkari',
            'price' => 50,
            'sku' => 'SK-' . uniqid(),
            'stock' => 10,
            'is_active' => true,
        ]);
    }

    private function buy(User $user, Product $product): void
    {
        $order = Order::create([
            'order_number' => 'RTB-' . uniqid(),
            'user_id' => $user->id,
            'customer_name' => 'Zaid',
            'customer_phone' => '966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 50,
            'total' => 75,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name_ar' => $product->name_ar,
            'unit_price' => 50,
            'quantity' => 1,
            'line_total' => 50,
        ]);
    }

    public function test_product_page_exposes_review_and_wishlist_state(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $this->buy($user, $product);

        $this->actingAs($user)
            ->get("/products/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/product')
                ->where('reviews.can_review', true)
                ->where('authed', true)
                ->where('wishlisted', false));
    }

    public function test_guest_cannot_post_a_review(): void
    {
        $product = $this->product();

        $this->post("/products/{$product->slug}/reviews", ['rating' => 5])
            ->assertRedirect('/login');
    }

    public function test_buyer_can_post_a_review_and_it_appears(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $this->buy($user, $product);

        $this->actingAs($user)
            ->post("/products/{$product->slug}/reviews", ['rating' => 5, 'title' => 'ممتاز', 'body' => 'رائع'])
            ->assertRedirect();

        $this->assertDatabaseHas('reviews', ['product_id' => $product->id, 'user_id' => $user->id, 'rating' => 5, 'is_approved' => true]);
    }

    public function test_non_buyer_review_is_rejected_with_flash_error(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $this->actingAs($user)
            ->post("/products/{$product->slug}/reviews", ['rating' => 5])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_wishlist_toggle_adds_then_removes(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $this->actingAs($user)->post("/wishlist/{$product->slug}/toggle")->assertRedirect();
        $this->assertDatabaseHas('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);

        $this->actingAs($user)->post("/wishlist/{$product->slug}/toggle")->assertRedirect();
        $this->assertDatabaseMissing('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_wishlist_index_lists_saved_products(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $this->actingAs($user)->post("/wishlist/{$product->slug}/toggle");

        $this->actingAs($user)
            ->get('/wishlist')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('account/wishlist')->has('items', 1));
    }
}
