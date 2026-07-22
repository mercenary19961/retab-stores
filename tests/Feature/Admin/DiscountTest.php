<?php

namespace Tests\Feature\Admin;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Discount\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DiscountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('secret'), 'role' => 'admin']);
    }

    private function product(float $price, ?int $categoryId = null): Product
    {
        $categoryId ??= Category::create(['name_ar' => 'ت', 'slug' => 'c-' . uniqid()])->id;

        return Product::create([
            'category_id' => $categoryId, 'name_ar' => 'م', 'slug' => 'p-' . uniqid(),
            'price' => $price, 'sku' => 'SK-' . uniqid(), 'smacc_sku' => 'SM-' . uniqid(), 'stock' => 10, 'is_active' => true,
        ]);
    }

    private function placeOrderWith(): \App\Models\Order
    {
        $product = $this->product(50);
        $cart = Cart::create(['session_token' => 's-' . uniqid()]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50]);

        return app(CheckoutService::class)->placeOrder($cart, ['name' => 'Z', 'phone' => '+966500000000'], ['country' => 'SA', 'city' => 'Riyadh']);
    }

    public function test_bulk_percentage_discounts_a_category_only(): void
    {
        $cat = Category::create(['name_ar' => 'تمور', 'slug' => 'dates']);
        $a = $this->product(100, $cat->id);
        $b = $this->product(50, $cat->id);
        $other = $this->product(80);

        app(DiscountService::class)->bulkApply('percentage', 20, null, $cat->id, null, null, null);

        $this->assertEquals(80.00, (float) $a->fresh()->sale_price);
        $this->assertEquals(40.00, (float) $b->fresh()->sale_price);
        $this->assertNull($other->fresh()->sale_price);
    }

    public function test_fixed_amount_discount(): void
    {
        $p = $this->product(50);
        app(DiscountService::class)->bulkApply('fixed', 12, null, null, null, null, null);

        $this->assertEquals(38.00, (float) $p->fresh()->sale_price);
    }

    public function test_max_cap_limits_a_percentage_discount(): void
    {
        $p = $this->product(200);
        // 50% would be 100 off, but capped at 30.
        app(DiscountService::class)->bulkApply('percentage', 50, 30, null, null, null, null);

        $this->assertEquals(170.00, (float) $p->fresh()->sale_price);
    }

    public function test_sale_window_controls_whether_the_product_is_on_sale(): void
    {
        $svc = app(DiscountService::class);

        $scheduled = $this->product(100);
        $svc->bulkApply('percentage', 20, null, null, Carbon::now()->addDay(), null, null);
        $this->assertFalse($scheduled->fresh()->isOnSale());
        $this->assertSame('scheduled', $scheduled->fresh()->saleStatus());

        $active = $this->product(100);
        $svc->bulkApply('percentage', 20, null, null, null, null, null);
        $this->assertTrue($active->fresh()->isOnSale());
    }

    public function test_csv_import_applies_per_row_percentages(): void
    {
        $p1 = $this->product(100);
        $p2 = $this->product(40);
        $svc = app(DiscountService::class);

        $path = tempnam(sys_get_temp_dir(), 'disc');
        file_put_contents($path, "sku,discount_percent\n{$p1->sku},30\n{$p2->sku},25\nGHOST,10\n");

        $rows = $svc->parse($path);
        $this->assertCount(2, $svc->diff($rows)['matched']);
        $svc->applyImport($rows, null, null, null);
        unlink($path);

        $this->assertEquals(70.00, (float) $p1->fresh()->sale_price);
        $this->assertEquals(30.00, (float) $p2->fresh()->sale_price);
    }

    public function test_clear_and_undo_restore_prices(): void
    {
        $p = $this->product(100);
        $svc = app(DiscountService::class);

        $log = $svc->bulkApply('percentage', 20, null, null, null, null, null);
        $this->assertEquals(80.00, (float) $p->fresh()->sale_price);
        $svc->undo($log, null);
        $this->assertNull($p->fresh()->sale_price);

        $svc->bulkApply('percentage', 20, null, null, null, null, null);
        $svc->clear([$p->id], null);
        $this->assertNull($p->fresh()->sale_price);
    }

    public function test_bulk_apply_route_works_for_admin(): void
    {
        $p = $this->product(100);

        $this->actingAs($this->admin())->post('/admin/discounts/apply', [
            'mode' => 'percentage', 'value' => 25, 'ends_at' => '2099-01-01T00:00',
        ])->assertSessionHas('success');

        $this->assertEquals(75.00, (float) $p->fresh()->sale_price);
        $this->assertTrue($p->fresh()->isOnSale());
    }

    public function test_automatic_free_shipping_waives_shipping_within_its_window(): void
    {
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, 25);

        // Active now → waived.
        Setting::set(CheckoutService::FREE_SHIPPING_ACTIVE_KEY, '1');
        $this->assertEquals(0.00, (float) $this->placeOrderWith()->shipping_fee);

        // Scheduled for the future → not yet waived.
        Setting::set(CheckoutService::FREE_SHIPPING_STARTS_KEY, Carbon::now()->addDay()->toDateTimeString());
        $this->assertEquals(25.00, (float) $this->placeOrderWith()->shipping_fee);

        // Turned off → not waived.
        Setting::set(CheckoutService::FREE_SHIPPING_ACTIVE_KEY, '0');
        Setting::set(CheckoutService::FREE_SHIPPING_STARTS_KEY, '');
        $this->assertEquals(25.00, (float) $this->placeOrderWith()->shipping_fee);
    }

    public function test_a_discount_can_bundle_free_shipping_over_its_window(): void
    {
        $this->product(100);
        $admin = $this->admin();

        // Opting in switches free shipping on for the discount's window.
        $this->actingAs($admin)->post('/admin/discounts/apply', [
            'mode' => 'percentage', 'value' => 20,
            'starts_at' => '2026-08-01T00:00', 'ends_at' => '2026-08-10T00:00',
            'free_shipping' => true,
        ])->assertSessionHas('success');

        $this->assertSame('1', Setting::get(CheckoutService::FREE_SHIPPING_ACTIVE_KEY));
        $this->assertNotEmpty(Setting::get(CheckoutService::FREE_SHIPPING_STARTS_KEY));

        // Not opting in leaves free shipping exactly as it was.
        Setting::set(CheckoutService::FREE_SHIPPING_ACTIVE_KEY, '0');
        $this->actingAs($admin)->post('/admin/discounts/apply', ['mode' => 'percentage', 'value' => 10])
            ->assertSessionHas('success');

        $this->assertSame('0', Setting::get(CheckoutService::FREE_SHIPPING_ACTIVE_KEY));
    }

    public function test_admin_flash_follows_the_admin_locale_cookie_not_the_session(): void
    {
        $admin = $this->admin();

        // English admin → English flash (even though the session default is Arabic).
        $this->actingAs($admin)->withUnencryptedCookie('admin_locale', 'en')
            ->post('/admin/discounts/free-shipping', ['active' => false])
            ->assertSessionHas('success', 'Free shipping settings saved.');

        // Arabic admin → Arabic flash.
        $this->actingAs($admin)->withUnencryptedCookie('admin_locale', 'ar')
            ->post('/admin/discounts/free-shipping', ['active' => false])
            ->assertSessionHas('success', 'تم حفظ إعدادات الشحن المجاني.');
    }
}
