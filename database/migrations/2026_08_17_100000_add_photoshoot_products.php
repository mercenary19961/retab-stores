<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * Create the four products the 2026-08-17 studio photoshoot revealed the catalogue
 * does not have. The shoot arrived with 24 shots; four of them photograph items
 * that were never in the Zid export, so the photos had nowhere to attach.
 *
 * A migration rather than a seeder or a one-off script, because these have to
 * exist on PRODUCTION and Railpack runs `migrate --force` on every deploy while it
 * never runs `db:seed` (same reasoning as the production-baseline migration).
 *
 * 🔑 Created as pure DRAFTS — `is_active = false`, and deliberately NOT
 * `is_coming_soon`. Coming-Soon would surface them on the storefront with the
 * price hidden and an "I want this" button, which is right for an item whose price
 * is known but stock is not; these have no price, weight or description yet, so
 * they belong in the admin Drafts workspace where the completeness badges
 * (needs_price / needs_description / needs_image) tell staff exactly what is
 * outstanding. The client fills those in and activates them.
 *
 * ⚠️ Idempotent on SKU, which is what makes it safe to re-run on every deploy.
 * The check uses `withTrashed()` because `products` soft-deletes: a trashed row
 * still holds its unique SKU and slug, so ignoring it would fail the insert
 * rather than skip it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The suite asserts against an empty catalogue baseline.
        if (app()->environment('testing')) {
            return;
        }

        $dates = Category::where('slug', 'dates')->value('id');
        $misc = Category::where('slug', 'assorted')->value('id');

        // A fresh database runs this before any catalogue exists. Nothing to
        // attach the products to, so leave it to the real import.
        if (! $dates || ! $misc) {
            return;
        }

        foreach ($this->products($dates, $misc) as $row) {
            if (Product::withTrashed()->where('sku', $row['sku'])->exists()) {
                continue;
            }

            Product::create([
                ...$row,
                'price' => 0,
                'stock' => 0,
                'is_active' => false,
                'is_coming_soon' => false,
            ]);
        }
    }

    public function down(): void
    {
        // forceDelete, not delete: a soft-deleted row keeps its unique slug and
        // SKU, which would block `up()` from ever recreating it.
        Product::withTrashed()
            ->whereIn('sku', ['RTB-0088', 'RTB-0089', 'RTB-0090', 'RTB-0091'])
            ->get()
            ->each
            ->forceDelete();
    }

    /**
     * SKUs continue the catalogue's own RTB-#### sequence (highest was RTB-0087).
     * Slugs are the Arabic-clean form `App\Support\ArabicSlug` produces, written
     * out literally so `catalog:clean-slugs` sees nothing to rewrite and no
     * redirect is ever recorded for them.
     *
     * @return list<array<string, mixed>>
     */
    private function products(int $dates, int $misc): array
    {
        return [
            [
                'sku' => 'RTB-0088',
                'name_ar' => 'تمر خلاصي بسبوسة',
                'name_en' => 'Khalasi Date Basbousa',
                'slug' => 'تمر-خلاصي-بسبوسة',
                'category_id' => $misc,
            ],
            [
                'sku' => 'RTB-0089',
                'name_ar' => 'كيكة الزعفران بالكريمة',
                'name_en' => 'Saffron Cake with Cream',
                'slug' => 'كيكة-الزعفران-بالكريمة',
                'category_id' => $misc,
            ],
            [
                // Name and ingredients read off the pack's own printed label:
                // دقيق فاخر · دخن بودر · زيت نباتي · معجون تمر خلاص · بكنج بودر · نكهة فانيلا
                'sku' => 'RTB-0090',
                'name_ar' => 'كيكة دخن',
                'name_en' => 'Millet Cake',
                'slug' => 'كيكة-دخن',
                'category_id' => $misc,
            ],
            [
                'sku' => 'RTB-0091',
                'name_ar' => 'دخيني درجة أولى 250 جرام',
                'name_en' => 'Grade 1 Dukhaini 250g',
                'slug' => 'دخيني-درجة-أولى-250-جرام',
                'category_id' => $dates,
            ],
        ];
    }
};
