<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let staff pin the couriers they actually work with to the top of the portal.
     *
     * 🔑 Deliberately NOT a behaviour flag, and that separation is the point. The
     * account lists sixteen carriers of which the store realistically uses two or
     * three, so finding one meant reading a price-sorted grid every time. This
     * only changes the ORDER they are read in — `is_enabled` remains the single
     * thing that decides whether a courier may carry a parcel, so a favourite that
     * is switched off is still never quoted and never auto-selected.
     *
     * Stored on the carrier rather than per admin: "these are the couriers we ship
     * with" is a fact about the store, not a preference of whoever is logged in,
     * and a second admin opening the page should see the same ordering as the
     * first. (A genuinely per-person preference, like the products card/table
     * switch, lives in localStorage instead.)
     */
    public function up(): void
    {
        Schema::table('shipping_carriers', function (Blueprint $table) {
            $table->boolean('is_favourite')->default(false)->after('is_enabled');

            // The portal orders by enabled, then favourite, then price. The first
            // two come from this table, so they are worth indexing together; price
            // is OTO's and is sorted client-side because it is never stored.
            $table->index(['is_favourite', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('shipping_carriers', function (Blueprint $table) {
            $table->dropIndex(['is_favourite', 'sort_order']);
            $table->dropColumn('is_favourite');
        });
    }
};
