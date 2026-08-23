<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot how many base stock units each order line consumes.
 *
 * With the shared-stock model, an option deducts `stock_units` per unit bought
 * (a 500g deducts 2, a carton deducts N). Snapshotting it on the order line
 * keeps stock deduction correct at confirm time even if the option was edited or
 * deleted meanwhile. Defaults to 1 — the count for a plain single-price product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('stock_units')->default(1)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('stock_units');
        });
    }
};
