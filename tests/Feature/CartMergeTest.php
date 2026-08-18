<?php

namespace Tests\Feature;

use App\Models\Cart;
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
            'slug' => 'p-'.$sku,
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
        Cart::create(['user_id' => $user->id])
            ->items()->create(['product_id' => $shared->id, 'quantity' => 3, 'unit_price' => 50]);

        OtpVerification::create([
            'phone' => '966500000000',
            'code' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->post('/login/whatsapp/verify', ['phone' => '966500000000', 'code' => '123456'])
            ->assertRedirect(route('account.dashboard'));

        $cart = Cart::where('user_id', $user->id)->firstOrFail();

        // Shared product: 3 (existing) + 2 (guest) = 5; guest-only product adopted.
        $this->assertSame(5, (int) $cart->items()->where('product_id', $shared->id)->value('quantity'));
        $this->assertSame(1, (int) $cart->items()->where('product_id', $guestOnly->id)->value('quantity'));

        // Guest cart is gone.
        $this->assertDatabaseMissing('carts', ['session_token' => $guestToken]);
    }

    /**
     * 🔴 Registration was the one entry point that did NOT merge — login and the
     * WhatsApp OTP flow both did — so a guest who filled a cart and then signed up
     * landed on an empty one. That is the exact path a first-time customer takes,
     * which is what made it expensive and easy to miss.
     */
    public function test_guest_cart_merges_into_user_on_registration(): void
    {
        $product = $this->product('REG');

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);
        $guestToken = session('cart_token');
        $this->assertNotNull($guestToken);

        $this->post('/register', [
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
        $user = User::where('email', 'new@example.com')->firstOrFail();

        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(2, (int) $cart->items()->where('product_id', $product->id)->value('quantity'));
        $this->assertDatabaseMissing('carts', ['session_token' => $guestToken]);
    }
}
