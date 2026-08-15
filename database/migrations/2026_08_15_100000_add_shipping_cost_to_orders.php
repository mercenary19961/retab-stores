<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the carrier actually charged us for this shipment.
 *
 * Distinct from `shipping_fee`, which is what the CUSTOMER paid — the single
 * flat GCC rate. The store deliberately absorbs the difference between the two
 * (see the Shipping decision in CLAUDE.md), but until now that difference was
 * invisible: the quoted price was fetched, used to sort carriers, and discarded.
 * Recording it makes the real margin per order answerable, which is what the
 * flat-rate decision should eventually be reviewed against.
 *
 * Nullable rather than defaulting to 0: orders shipped before this existed have
 * no known cost, and 0 would assert we shipped them for free.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shipping_cost', 10, 2)->nullable()->after('shipping_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_cost');
        });
    }
};
