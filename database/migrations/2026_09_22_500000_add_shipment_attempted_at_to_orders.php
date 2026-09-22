<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks that a shipment booking was STARTED but never finished.
     *
     * 🔴 The gap it closes costs real money. `fulfill()` books the parcel with OTO
     * and only then writes the tracking number to the order, and between those two
     * steps anything can fail: the database, or `createShipment` itself, which
     * throws "no tracking number was returned" AFTER OTO has already created the
     * shipment. Either way the parcel exists at OTO while `tracking_number` is
     * still null locally — and the duplicate guard at the top of `fulfill()` reads
     * exactly that column. So the admin, seeing an order that still says "Ship",
     * clicks it again and books a SECOND parcel: two labels, two collections, two
     * carrier charges, and nothing anywhere saying so.
     *
     * 🔑 Why a marker rather than simply asking OTO every time whether a shipment
     * exists: a recalled shipment is a supported flow (`ShippingService::cancel`
     * clears the tracking and returns the order to `confirmed` so it can be
     * shipped again), and it is NOT known whether OTO keeps reporting the recalled
     * parcel's tracking number on `orderDetails` afterwards. If it does, an
     * unconditional check would "adopt" the dead parcel and silently never book
     * the replacement. This column depends on none of that: it is set only while
     * an attempt is genuinely in flight, and cleared the moment one succeeds, so a
     * clean re-ship never consults OTO at all.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('shipment_attempted_at')->nullable()->after('shipping_label_url');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipment_attempted_at');
        });
    }
};
