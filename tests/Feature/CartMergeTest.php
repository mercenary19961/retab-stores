<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OtpVerification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CartMergeTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $sku): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name_ar' => 'منتج',
            'slug' => 'p-' . $sku,
            'price' => 50,
            'sku' => $sku,
            'stock' => 50,
            'is_active' => true,
        ]);
    }

    public function test_guest_cart_merges_into_user_on_whatsapp_login(): void
    {
        $shared = $this->product('SHARED');
        $guestOnly = $this->product('GUEST');

        // Guest builds a cart.
        $this->post('/cart', ['product_id' => $shared->id, 'quantity' => 2]);
        $this->post('/cart', ['product_id' => $guestOnly->id, 'quantity' => 1]);
        $guestToken = session('cart_token');
        $this->assertNotNull($guestToken);

        // A returning user who already has the shared product in their cart.
        $user = User::factory()->create(['phone' => '966500000000', 'role' => 'customer']);
        \App\Models\Cart::create(['user_id' => $user->id])
            ->items()->create(['product_id' => $shared->id, 'quantity' => 3, 'unit_price' => 50]);

        OtpVerification::create([
            'phone' => '966500000000',
            'code' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->post('/login/whatsapp/verify', ['phone' => '966500000000', 'code' => '123456'])
            ->assertRedirect(route('account.dashboard'));

        $cart = \App\Models\Cart::where('user_id', $user->id)->firstOrFail();

        // Shared product: 3 (existing) + 2 (guest) = 5; guest-only product adopted.
        $this->assertSame(5, (int) $cart->items()->where('product_id', $shared->id)->value('quantity'));
        $this->assertSame(1, (int) $cart->items()->where('product_id', $guestOnly->id)->value('quantity'));

        // Guest cart is gone.
        $this->assertDatabaseMissing('carts', ['session_token' => $guestToken]);
    }
}
