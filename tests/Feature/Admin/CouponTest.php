<?php

namespace Tests\Feature\Admin;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('secret'), 'role' => 'admin']);
    }

    private function product(): Product
    {
        $cat = Category::create(['name_ar' => 'تمور', 'slug' => 'c-' . uniqid()]);

        return Product::create([
            'category_id' => $cat->id, 'name_ar' => 'سكري', 'slug' => 'p-' . uniqid(),
            'price' => 50, 'sku' => 'SK-' . uniqid(), 'smacc_sku' => 'SM-' . uniqid(), 'stock' => 100,
        ]);
    }

    public function test_admin_creates_a_percentage_coupon_and_the_code_is_uppercased(): void
    {
        $this->actingAs($this->admin())->post('/admin/coupons', [
            'code' => 'ramadan15',
            'type' => 'percentage',
            'value' => 15,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('coupons', ['code' => 'RAMADAN15', 'type' => 'percentage', 'value' => 15, 'source' => 'manual']);
    }

    public function test_percentage_over_100_and_bad_date_window_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'TOOBIG', 'type' => 'percentage', 'value' => 150, 'is_active' => true,
        ])->assertSessionHasErrors('value');

        $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'BADWIN', 'type' => 'fixed', 'value' => 10, 'is_active' => true,
            'starts_at' => '2026-08-01T00:00', 'expires_at' => '2026-07-01T00:00',
        ])->assertSessionHasErrors('expires_at');

        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_free_delivery_coupon_waives_the_shipping_fee(): void
    {
        $product = $this->product();
        Coupon::create(['code' => 'FREESHIP', 'type' => 'free_shipping', 'value' => 0, 'is_active' => true]);
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, 25);

        $cart = Cart::create(['session_token' => 'g-1']);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 50]);

        $order = app(CheckoutService::class)->placeOrder(
            $cart, ['name' => 'Zaid', 'phone' => '+966500000000'], ['country' => 'SA', 'city' => 'Riyadh'], 'FREESHIP',
        );

        $this->assertEquals(100.00, (float) $order->subtotal);
        $this->assertEquals(0.00, (float) $order->discount_total); // no subtotal discount
        $this->assertEquals(0.00, (float) $order->shipping_fee);   // shipping waived
        $this->assertEquals(100.00, (float) $order->total);
    }

    public function test_per_user_limit_is_enforced_at_checkout(): void
    {
        $user = User::create(['name' => 'Cust', 'email' => 'c@test.com', 'password' => bcrypt('x')]);
        $product = $this->product();
        Coupon::create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 5, 'per_user_limit' => 1, 'is_active' => true]);
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, 0);

        $place = function () use ($user, $product) {
            $cart = Cart::create(['session_token' => 's-' . uniqid(), 'user_id' => $user->id]);
            $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50]);

            return app(CheckoutService::class)->placeOrder(
                $cart, ['name' => 'Cust', 'phone' => '+966500000000'], ['country' => 'SA', 'city' => 'Riyadh'], 'ONCE',
            );
        };

        $place(); // first use OK

        $this->expectException(\RuntimeException::class);
        $place(); // second use by the same user is blocked
    }

    public function test_used_coupon_cannot_be_deleted_but_unused_can(): void
    {
        $admin = $this->admin();
        $unused = Coupon::create(['code' => 'UNUSED', 'type' => 'fixed', 'value' => 5, 'is_active' => true]);
        $used = Coupon::create(['code' => 'USED', 'type' => 'fixed', 'value' => 5, 'is_active' => true]);
        $used->redemptions()->create(['user_id' => null, 'order_id' => null, 'discount_amount' => 5, 'redeemed_at' => now()]);

        $this->actingAs($admin)->delete("/admin/coupons/{$used->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('coupons', ['id' => $used->id]);

        $this->actingAs($admin)->delete("/admin/coupons/{$unused->id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('coupons', ['id' => $unused->id]);
    }
}
