<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Discount\DiscountService;
use Illuminate\Database\Seeder;

/**
 * Sample coupons + product discounts for previewing the Coupons and Discounts
 * admin pages populated. NOT wired into DatabaseSeeder — run explicitly:
 *
 *   php artisan db:seed --class=PromotionSeeder
 *
 * Coupons are upserted by code (idempotent). Discounts re-apply cleanly; the
 * automatic free-shipping promo is left SCHEDULED (future) so it shows on the
 * page without waiving live-checkout shipping.
 */
class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('role', 'admin')->value('id');

        $this->coupons($adminId);
        $this->discounts($adminId);
        $this->automaticFreeShipping();
    }

    /** A spread of coupon types + lifecycle states. */
    private function coupons(?int $adminId): void
    {
        $coupons = [
            ['code' => 'RAMADAN15', 'type' => 'percentage', 'value' => 15, 'max_discount' => 30, 'min_order_total' => 100, 'usage_limit' => 500, 'per_user_limit' => 1,
                'description_ar' => 'خصم رمضان 15٪', 'description_en' => 'Ramadan 15% off'],
            ['code' => 'WELCOME20', 'type' => 'fixed', 'value' => 20, 'min_order_total' => 80, 'usage_limit' => 200,
                'description_ar' => 'خصم ترحيبي 20 ريال', 'description_en' => 'Welcome 20 SAR off'],
            ['code' => 'FREESHIP', 'type' => 'free_shipping', 'value' => 0, 'usage_limit' => 1000,
                'description_ar' => 'توصيل مجاني', 'description_en' => 'Free delivery'],
            ['code' => 'EID25', 'type' => 'percentage', 'value' => 25, 'max_discount' => 60, 'starts_at' => now()->addDays(7), 'expires_at' => now()->addDays(21),
                'description_ar' => 'عرض العيد 25٪', 'description_en' => 'Eid 25% off'], // scheduled
            ['code' => 'SUMMER10', 'type' => 'percentage', 'value' => 10, 'expires_at' => now()->subDays(3),
                'description_ar' => 'خصم الصيف', 'description_en' => 'Summer 10% off'], // expired
            ['code' => 'VIP50', 'type' => 'fixed', 'value' => 50, 'min_order_total' => 300, 'per_user_limit' => 1, 'usage_limit' => 50,
                'description_ar' => 'خصم كبار العملاء 50 ريال', 'description_en' => 'VIP 50 SAR off'],
        ];

        foreach ($coupons as $c) {
            Coupon::updateOrCreate(
                ['code' => $c['code']],
                $c + ['source' => 'manual', 'channel' => 'online', 'is_active' => true, 'created_by' => $adminId],
            );
        }
    }

    /** Store-wide + category discounts, then vary two windows to show scheduled/expired. */
    private function discounts(?int $adminId): void
    {
        $svc = app(DiscountService::class);

        // Everything on sale at 15% for the next 30 days (writes one history entry).
        $svc->bulkApply('percentage', 15, null, null, null, now()->addDays(30), $adminId);

        // Deepen one category to 25% (a second, category-scoped history entry).
        $assorted = Category::where('slug', 'assorted')->value('id');
        if ($assorted) {
            $svc->bulkApply('percentage', 25, null, $assorted, null, now()->addDays(10), $adminId);
        }

        // Show one scheduled + one expired sale for the status badges.
        $onSale = Product::where('is_active', true)->whereNotNull('sale_price')->orderBy('id')->get();
        $onSale->first()?->update(['sale_starts_at' => now()->addDays(5), 'sale_ends_at' => now()->addDays(19)]); // scheduled
        $onSale->last()?->update(['sale_starts_at' => now()->subDays(12), 'sale_ends_at' => now()->subDays(2)]); // expired
    }

    /** Automatic free shipping, scheduled a few days out (visible, no live impact). */
    private function automaticFreeShipping(): void
    {
        Setting::set(CheckoutService::FREE_SHIPPING_ACTIVE_KEY, '1');
        Setting::set(CheckoutService::FREE_SHIPPING_STARTS_KEY, now()->addDays(3)->toDateTimeString());
        Setting::set(CheckoutService::FREE_SHIPPING_ENDS_KEY, now()->addDays(10)->toDateTimeString());
    }
}
