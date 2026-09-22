<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\ChangeLog\ChangeLogService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('secret'), 'role' => 'admin']);
    }

    private function product(): Product
    {
        $cat = Category::create(['name_ar' => 'تمور', 'slug' => 'c-'.uniqid()]);

        return Product::create([
            'category_id' => $cat->id, 'name_ar' => 'سكري', 'slug' => 'p-'.uniqid(),
            'price' => 50, 'sku' => 'SK-'.uniqid(), 'smacc_sku' => 'SM-'.uniqid(), 'stock' => 100,
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
        $user = User::forceCreate(['name' => 'Cust', 'email' => 'c@test.com', 'password' => bcrypt('x')]);
        $product = $this->product();
        Coupon::create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 5, 'per_user_limit' => 1, 'is_active' => true]);
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, 0);

        $place = function () use ($user, $product) {
            $cart = Cart::create(['session_token' => 's-'.uniqid(), 'user_id' => $user->id]);
            $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50]);

            return app(CheckoutService::class)->placeOrder(
                $cart, ['name' => 'Cust', 'phone' => '+966500000000'], ['country' => 'SA', 'city' => 'Riyadh'], 'ONCE',
            );
        };

        $place(); // first use OK

        $this->expectException(\RuntimeException::class);
        $place(); // second use by the same user is blocked
    }

    public function test_toggle_flips_the_active_state(): void
    {
        $admin = $this->admin();
        $coupon = Coupon::create(['code' => 'TOGGLE', 'type' => 'fixed', 'value' => 5, 'is_active' => true]);

        $this->actingAs($admin)->from('/admin/coupons')
            ->patch("/admin/coupons/{$coupon->id}/toggle")
            ->assertRedirect('/admin/coupons')->assertSessionHas('success');
        $this->assertFalse($coupon->fresh()->is_active);

        $this->actingAs($admin)->patch("/admin/coupons/{$coupon->id}/toggle");
        $this->assertTrue($coupon->fresh()->is_active);
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
        // Soft-deleted: off every list and unusable at checkout, but restorable.
        $this->assertNull(Coupon::find($unused->id));
        $this->assertNotNull(Coupon::withTrashed()->find($unused->id));
    }

    /**
     * 🔴 The one thing a soft delete could plausibly have broken here: a trashed
     * row still occupies the unique index, so without scoping the rule to live
     * rows the client could never reuse a code they had deleted — and the error
     * would name a coupon that is not on any screen.
     */
    public function test_a_deleted_coupons_code_can_be_used_again(): void
    {
        $admin = $this->admin();
        $original = Coupon::create(['code' => 'RAMADAN', 'type' => 'fixed', 'value' => 5, 'is_active' => true]);

        $this->actingAs($admin)->delete("/admin/coupons/{$original->id}")->assertSessionHas('success');

        $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'RAMADAN',
            'type' => 'fixed',
            'value' => 10,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Coupon::where('code', 'RAMADAN')->count());
        $this->assertSame('10.00', Coupon::where('code', 'RAMADAN')->value('value'));
    }

    public function test_deleting_a_coupon_is_recorded_and_can_be_undone(): void
    {
        $admin = $this->admin();
        $coupon = Coupon::create(['code' => 'WELCOME', 'type' => 'fixed', 'value' => 5, 'is_active' => true]);

        $this->actingAs($admin)->delete("/admin/coupons/{$coupon->id}")->assertSessionHas('success');

        $log = ActivityLog::where('subject_type', Coupon::class)
            ->where('subject_id', $coupon->id)
            ->where('action', ActivityLog::ACTION_DELETED)
            ->firstOrFail();

        $this->assertSame('WELCOME', $log->label);
        $this->assertTrue(app(ChangeLogService::class)->revert($log)->ok);
        $this->assertNotNull(Coupon::find($coupon->id));
    }
}
