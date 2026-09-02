<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Correct the Khalas Ushaiger carton data, confirmed against the client's own
 * live Zid store on 2026-09-02.
 *
 * 🔴 WHY THE STORED VALUE WAS WRONG, because the mistake generalises:
 * `catalog:merge-variants` derived each box's `stock_units` as
 * `carton price ÷ unit price`. That is only the pack count when the carton
 * carries NO bulk discount. Two of these three had none and came out right; the
 * 1kg has one, so the ratio undercounted it:
 *
 *     250g   69.00 ÷  5.75 = 12   ✓ no discount
 *     500g  120.00 ÷ 10.00 = 12   ✓ no discount
 *     1kg   140.00 ÷ 20.00 =  7   ✗ really 8 packs — 8 × 20.00 = 160 list,
 *                                    sold at 140.00, a 12.5% carton discount
 *
 * Under-deducting stock is the expensive direction (the shop believes it holds
 * more than it does), so this is a real inventory fix, not tidying. The prices
 * themselves were never wrong: 5.75/69.00, 10.00/120.00 and 20.00/140.00 all
 * match retabstore.com exactly.
 *
 * 🔑 The lasting rule: a pack count CANNOT be derived from price. Any discounted
 * carton undercounts silently. Counts come from the client, and the admin's
 * Units/box field is where they are corrected from here on.
 *
 * Also activates the box option on the three products. Their cartons are all sold
 * on the live Zid store; the options were created inactive only because
 * `catalog:hide-unphotographed` had hidden the carton ROWS for lacking client
 * photography, which the merge faithfully inherited. That is a photography
 * decision, not a decision to stop selling cartons. No customer impact either
 * way while the parent products are hidden — a hidden product is unbuyable, so
 * this only sets the right state for whenever they are published.
 *
 * Scoped to explicit SKUs and idempotent (Railpack runs `migrate --force` on
 * every deploy). No `testing` guard: it only updates existing rows, and the test
 * database has none of these SKUs at migration time.
 */
return new class extends Migration
{
    /** SKU => packs per carton, supplied by the client. */
    private const PACK_COUNTS = [
        'RTB-0003' => 8,  // 1kg
        'RTB-0009' => 8,  // 1kg "Pack" — the duplicate listing; corrected in case it is kept
    ];

    /**
     * The three sizes the client confirmed are separate sellable products, each
     * keeping its own carton. RTB-0009 is deliberately absent: it is an
     * unresolved duplicate of RTB-0003 and must not quietly become sellable.
     */
    private const SELLABLE_BOXES = ['RTB-0003', 'RTB-0007', 'RTB-0044'];

    public function up(): void
    {
        foreach (self::PACK_COUNTS as $sku => $packs) {
            DB::table('product_options')
                ->whereIn('product_id', DB::table('products')->select('id')->where('sku', $sku))
                ->where('is_box', true)
                ->update(['stock_units' => $packs]);
        }

        DB::table('product_options')
            ->whereIn('product_id', DB::table('products')->select('id')->whereIn('sku', self::SELLABLE_BOXES))
            ->where('is_box', true)
            ->update(['is_active' => true]);
    }

    public function down(): void
    {
        // Deliberately irreversible. Restoring stock_units to the derived 7 would
        // only reinstate the under-deduction this exists to fix.
    }
};
