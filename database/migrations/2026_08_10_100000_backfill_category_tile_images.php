<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the homepage tile image on any category still missing one.
 *
 * Production was showing only FOUR category tiles instead of five: `الشوابير`
 * (slug `rusks`) had `categories.image = NULL`, and `ShopController::index`
 * builds `featuredCategories` with `whereNotNull('image')` — so the row is
 * dropped from the query entirely rather than falling back to the 🌴 placeholder
 * the component renders for a null image. That is why it looked like a broken
 * asset when it was actually a missing column value: the category itself is
 * present, active, and still appears as a filter chip on /shop.
 *
 * The values match `ZidCatalogImporter::CATEGORY_MAP`, which is what sets them on
 * import, but they are **hardcoded here on purpose**. A migration is a frozen
 * record of a change; reading the constant would let a future edit to the importer
 * silently alter what this already-run migration meant.
 *
 * ⚠️ Only fills rows where `image IS NULL`. Categories have no admin UI today, so
 * every value comes from the importer — but if editing is added later, this must
 * never overwrite a deliberate choice. That also makes it idempotent, which
 * matters because Railpack runs `migrate --force` on every deploy.
 *
 * No `testing` guard needed (unlike the baseline-data migration, which seeds
 * rows): this only updates existing categories, and the test database has none at
 * migration time.
 */
return new class extends Migration
{
    /**
     * Category slug => homepage tile image. `assorted` is deliberately absent: the
     * importer maps it to null, so it is meant to stay off the homepage.
     */
    private const TILE_IMAGES = [
        'dates' => '/images/categories/sukkari.webp',
        'stuffed-dates' => '/images/categories/stuffed-dates.webp',
        'rusks' => '/images/categories/rusks.webp',
        'boxes' => '/images/categories/boxes.webp',
        'occasion-gifts' => '/images/categories/occasion-gifts.webp',
    ];

    public function up(): void
    {
        foreach (self::TILE_IMAGES as $slug => $image) {
            DB::table('categories')
                ->where('slug', $slug)
                ->whereNull('image')
                ->update(['image' => $image]);
        }
    }

    public function down(): void
    {
        // Reference data. Nulling these back out would only re-break the homepage.
    }
};
