<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed `products.base_weight_grams` for every product whose ENGLISH name already
 * states its pack size, so the storefront can name the default choice by its size
 * ("250غم") instead of the generic «الأصلي» from day one.
 *
 * Derived once by parsing the names, then FROZEN here as an explicit map rather
 * than re-parsed at run time — same reasoning as the category-tile backfill: a
 * migration is a record of a change, and a parser that drifts would silently
 * change what an already-run migration meant. It is also reviewable, which a
 * regex over 79 product names is not.
 *
 * ⚠️ These are pack sizes read off the name, so they are correct even where the
 * PRICE is not: RTB-0038/0039/0068/0069 carry carton-scale prices on a
 * single-pack name (known debris, all hidden). Labelling those "250غم" next to
 * 240.00 makes the mismatch MORE visible, not less, which is the right direction.
 *
 * ⚠️ Only fills rows that are still null, so a value an admin has since corrected
 * is never overwritten. That also makes it idempotent, which matters because
 * Railpack runs `migrate --force` on every deploy.
 */
return new class extends Migration
{
    /** Product SKU => the weight its own price is for, in grams. */
    private const BASE_WEIGHTS = [
        'RTB-0001' => 500,
        'RTB-0003' => 1000,
        'RTB-0004' => 250,
        'RTB-0007' => 500,
        'RTB-0009' => 1000,
        'RTB-0011' => 500,
        'RTB-0035' => 1000,
        'RTB-0038' => 1000,
        'RTB-0039' => 250,
        'RTB-0044' => 250,
        'RTB-0046' => 1500,
        'RTB-0054' => 5,
        'RTB-0056' => 1000,
        'RTB-0057' => 1000,
        'RTB-0068' => 500,
        'RTB-0069' => 1000,
        'RTB-0070' => 500,
        'RTB-0071' => 1000,
        'RTB-0073' => 250,
        'RTB-0077' => 1000,
        'RTB-0079' => 500,
        'RTB-0083' => 250,
        'RTB-0091' => 250,
    ];

    public function up(): void
    {
        foreach (self::BASE_WEIGHTS as $sku => $grams) {
            DB::table('products')
                ->where('sku', $sku)
                ->whereNull('base_weight_grams')
                ->update(['base_weight_grams' => $grams]);
        }
    }

    public function down(): void
    {
        // Reference data. Nulling it back out would only return the storefront to
        // an unlabelled default choice.
    }
};
