<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product sellable options (sizes + packaging), replacing the old
 * one-product-per-variant bloat: a single product now carries a list like
 * 250g / 500g / 1kg / carton, and the customer picks one.
 *
 * Pricing: weight options auto-scale from the smallest option's price
 * (base price × amount ÷ base amount), each overridable; a carton has no
 * weight so its price is always manual (amount = null).
 *
 * Stock: the product keeps one shared `stock`; each option consumes
 * `stock_units` per purchase (base = 1, 500g = 2, carton = N). This is the
 * "shared stock deducted by amount" model chosen deliberately. `smacc_sku`
 * is the SEAM for later per-option SMACC sync, unused for now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('label_ar');
            $table->string('label_en')->nullable();

            // Weight in grams — drives auto price-scaling and display. Null for a
            // non-weight option (a carton/box), whose price is always manual.
            $table->unsignedInteger('amount')->nullable();

            $table->decimal('price', 10, 2);
            // Whether `price` was hand-set vs auto-scaled — the admin form reads
            // this to know which options to recompute when the base price changes.
            $table->boolean('price_overridden')->default(false);

            // How many base stock units one purchase of this option consumes.
            $table->unsignedInteger('stock_units')->default(1);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            // SEAM: future per-option SMACC code. Nullable + unenforced for now.
            $table->string('smacc_sku', 100)->nullable();

            $table->timestamps();

            $table->index(['product_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
