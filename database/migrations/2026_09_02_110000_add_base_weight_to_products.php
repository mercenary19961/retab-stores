<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What weight the product's own price is for.
 *
 * 🔑 Until now the product price had NO weight attached to it anywhere. The
 * storefront therefore had to label the always-present default choice with a
 * generic «الأصلي» / "Original", which tells a shopper nothing about what they
 * are buying — the size lived only in the product NAME, which is why the name
 * could not safely drop it.
 *
 * With this set, the picker names the base choice by its size ("250غم") and the
 * weight becomes free to leave the name.
 *
 * Grams, matching `product_options.amount`, rather than a free-text label: a
 * number formats consistently in both languages from one i18n key, where typed
 * labels drift ("250 جرام" / "250غم" / "250g" for the same thing).
 *
 * Nullable, and null keeps today's behaviour exactly — that is what makes it
 * optional for the many products (nuts, boxes, pastes) that have no meaningful
 * single weight.
 *
 * ⏭ It is also the missing anchor for price auto-scaling. `scaleFromBasePrice`
 * currently treats the SMALLEST OPTION as the base, so on a 250g product priced
 * 5.75 the "+ 500g" preset produces 5.75 instead of 11.50. Wiring that to this
 * column would fix it, and is deliberately left as its own change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('base_weight_grams')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('base_weight_grams');
        });
    }
};
