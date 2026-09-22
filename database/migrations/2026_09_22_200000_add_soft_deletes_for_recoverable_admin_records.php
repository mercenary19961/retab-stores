<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make the admin panel's deletes recoverable.
 *
 * 🔑 THE POINT IS NOT THE COLUMN, IT IS THE FILES. Before this, deleting a hero
 * slide ran `Media::delete()` on its image, phone art, video and poster and THEN
 * dropped the row — so even a perfect change-log entry could not have undone it,
 * because the bytes were already gone from R2. A soft delete is what buys the
 * window in which a revert still has something to restore; `media:purge-trash`
 * removes the files later, once the window has closed.
 *
 * ⚠️ Every table here previously hard-deleted, so `deleted_at` is nullable and
 * every existing row is (correctly) not trashed. Nothing needs backfilling.
 *
 * ⚠️ A trashed row still occupies any UNIQUE index it holds — the same trade
 * `products` already lives with for its slug and SKU. Where that would block the
 * client (a coupon code being unusable because a deleted coupon still holds it)
 * the validation rule is scoped to un-trashed rows; see CouponController.
 *
 * Deliberately NOT included: `users` already soft-deletes (UserController simply
 * force-deleted through it); `orders`/`order_returns`/`payments` are state
 * machines with their own append-only audit and are never deleted from the panel.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        // Hold files → these are the ones the purge window exists for.
        'hero_slides',
        'event_hero_banners',
        'product_images',
        'store_events',       // cascades to banner + offer artwork
        // No files, so their trashed rows are tiny and are kept indefinitely.
        'announcements',
        'coupons',
        'client_reviews',
        'product_options',
        'reviews',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                continue; // already recoverable
            }

            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
