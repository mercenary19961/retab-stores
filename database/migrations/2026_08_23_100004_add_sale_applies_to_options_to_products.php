<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a product's sale/discount extends to its size options.
 *
 * A discount is on the product's ORIGINAL price. By default it does NOT cascade
 * to the derived size prices — the admin opts in per product (a toggle in the
 * options editor) when a size should carry the discount too. Defaults to false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('sale_applies_to_options')->default(false)->after('sale_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sale_applies_to_options');
        });
    }
};
