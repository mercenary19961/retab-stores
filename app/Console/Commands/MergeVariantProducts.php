<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Redirect;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-time (idempotent) catalogue cleanup: fold the Zid import's flattened
 * "<name> - Carton" / "<name> - Single" PRODUCT PAIRS into ONE product carrying a
 * Box option, which is how packaging has been modelled since product options
 * shipped (see the create_product_options migration).
 *
 * Zid kept carton and single as variant OPTIONS of a single product; the CSV
 * export flattened each variant into its own row, so the catalogue ended up
 * selling the same dates twice at wildly different prices (Sukkari 500g at 11.50
 * and at 138.00). That is what this undoes.
 *
 * 🔑 The SINGLE survives, never the carton. Its price is the per-unit price, which
 * is exactly what a product's own `price` means once options exist — the
 * always-present default choice the storefront opens on. The carton becomes a Box
 * option priced by hand beside it.
 *
 * 🔑 The survivor keeps its SLUG. It is already indexed and renaming it would cost
 * the ranking for nothing; only the absorbed carton's slug needs a home, and it
 * gets a 301 to the survivor so the old URL keeps working (same reasoning as
 * CleanProductSlugs, which is the tool for slug hygiene — deliberately not this
 * one's job).
 *
 * ⚠️ STOCK. The two rows each carried their own count of the same physical dates,
 * so only the single's survives, as the shared base-unit pool. The Box option then
 * consumes `stock_units` per sale — derived from the price ratio (carton ÷ single),
 * which is the only evidence in the data of how many units are in a carton. It is
 * a DERIVED number, not a known one: some pairs do not divide cleanly, so every
 * non-integer ratio is reported for the client to correct in the admin.
 *
 * Safe to re-run: merged names no longer carry a suffix, and a survivor that
 * already has a box option is skipped.
 */
class MergeVariantProducts extends Command
{
    protected $signature = 'catalog:merge-variants
        {--apply : Write the changes (default is a dry-run preview)}';

    protected $description = 'Merge flattened "- Carton"/"- Single" product pairs into one product with a Box option';

    /** Name suffixes that mark a row as the CARTON half of a flattened pair. */
    private const CARTON_SUFFIXES = ['carton', 'box', 'كرتون', 'الكرتون', 'بوكس'];

    /** Name suffixes that mark a row as the SINGLE (per-unit) half. */
    private const SINGLE_SUFFIXES = ['single', 'unit', 'حبة', 'حبه', 'الحبة', 'الحبه'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $groups = $this->groupBySuffix(Product::with('options')->orderBy('id')->get());

        $pairs = [];
        $orphans = [];
        $skipped = [];

        foreach ($groups as $group) {
            $cartons = $group->where('kind', 'carton')->values();
            $singles = $group->where('kind', 'single')->values();

            // More than one of either half means the base name is not actually
            // identifying one product. Refusing beats guessing which to keep.
            if ($cartons->count() > 1 || $singles->count() > 1) {
                $skipped[] = $group->first()['base_label'].' — '.$cartons->count().' carton / '.$singles->count().' single rows';

                continue;
            }

            $carton = $cartons->first();
            $single = $singles->first();

            if ($carton && $single) {
                /** @var Product $survivor */
                $survivor = $single['product'];

                if ($survivor->options->where('is_box', true)->isNotEmpty()) {
                    continue; // already merged
                }

                $unitPrice = (float) $survivor->price;
                $ratio = $unitPrice > 0 ? (float) $carton['product']->price / $unitPrice : 0.0;

                $pairs[] = [
                    'single' => $survivor,
                    'carton' => $carton['product'],
                    'base_ar' => $single['base_ar'],
                    'base_en' => $single['base_en'],
                    'units' => max(1, (int) round($ratio)),
                    'ratio' => $ratio,
                ];

                continue;
            }

            $only = $carton ?? $single;
            $orphans[] = [
                'product' => $only['product'],
                'base_ar' => $only['base_ar'],
                'base_en' => $only['base_en'],
            ];
        }

        if ($pairs === [] && $orphans === []) {
            $this->info('No flattened carton/single products left. Nothing to do.');

            return self::SUCCESS;
        }

        $this->renderPairs($pairs);
        $this->renderOrphans($orphans);

        foreach ($skipped as $note) {
            $this->warn('Skipped (ambiguous): '.$note);
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('Dry run — re-run with --apply to write these changes.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($pairs, $orphans) {
            foreach ($pairs as $pair) {
                $this->merge($pair);
            }
            foreach ($orphans as $orphan) {
                $this->renameOnly($orphan);
            }
        });

        $this->newLine();
        $this->info('Merged '.count($pairs).' pair(s) and renamed '.count($orphans).' unpaired product(s).');

        return self::SUCCESS;
    }

    /**
     * Fold one pair: the single absorbs the carton as a Box option, the carton's
     * URL gets a 301 to the survivor, and the carton row is soft-deleted.
     *
     * @param  array<string, mixed>  $pair
     */
    private function merge(array $pair): void
    {
        /** @var Product $survivor */
        $survivor = $pair['single'];
        /** @var Product $carton */
        $carton = $pair['carton'];

        $survivor->fill($this->mergedAttributes($pair, $carton));
        $survivor->save();

        $survivor->options()->create([
            'label_ar' => 'كرتون',
            'label_en' => 'Box',
            // A box has no weight, so it is never auto-scaled and its price is
            // always the hand-set one — hence price_overridden, which is what
            // stops the editor recomputing it from the product price.
            'amount' => null,
            'is_box' => true,
            'price' => $carton->price,
            'price_overridden' => true,
            'stock_units' => $pair['units'],
            'is_active' => true,
            'sort_order' => 1,
            // SEAM: the carton had its own SMACC code; keep it on the option
            // rather than dropping it, since per-option sync is the plan.
            'smacc_sku' => $carton->smacc_sku,
        ]);

        // The carton's slug may be indexed, so it gets a permanent redirect to the
        // survivor rather than becoming a 404. firstOrCreate keeps re-runs safe.
        Redirect::firstOrCreate(
            ['from_slug' => $carton->slug],
            ['product_id' => $survivor->id, 'status' => 301],
        );

        $carton->delete();
    }

    /**
     * A carton (or single) with no counterpart: there is nothing to merge, so only
     * the now-meaningless suffix comes off the name. Price and stock are left
     * exactly as they are — inventing a per-unit price for a carton-priced product
     * would be making data up.
     *
     * @param  array<string, mixed>  $orphan
     */
    private function renameOnly(array $orphan): void
    {
        /** @var Product $product */
        $product = $orphan['product'];
        $product->name_ar = $orphan['base_ar'];

        if ($orphan['base_en'] !== null) {
            $product->name_en = $orphan['base_en'];
        }

        $product->save();
    }

    /**
     * The survivor's new attributes: the suffix-free names, plus anything the
     * carton row had that the survivor is missing. Only-empty fills, matching the
     * importer's convention — a value the survivor already holds is never
     * overwritten by the row being discarded.
     *
     * @param  array<string, mixed>  $pair
     * @return array<string, mixed>
     */
    private function mergedAttributes(array $pair, Product $carton): array
    {
        /** @var Product $survivor */
        $survivor = $pair['single'];

        $attributes = ['name_ar' => $pair['base_ar']];

        if ($pair['base_en'] !== null) {
            $attributes['name_en'] = $pair['base_en'];
        }

        foreach (['name_en', 'description_ar', 'description_en', 'short_description_ar', 'short_description_en', 'smacc_sku', 'barcode'] as $field) {
            $mine = trim((string) ($attributes[$field] ?? $survivor->{$field}));
            $theirs = trim((string) $carton->{$field});

            if ($mine === '' && $theirs !== '') {
                $attributes[$field] = $theirs;
            }
        }

        return $attributes;
    }

    /**
     * Bucket every suffixed product by (category, base name). The English name is
     * the grouping key when there is one because it is the more consistent of the
     * two in this catalogue; Arabic is the fallback for a row without one.
     *
     * ⚠️ The category is part of the key on purpose: "Khalas Ushaiger Grade 1 250g"
     * exists as a pair in BOTH Premium Dates and Assorted Products at different
     * prices, and collapsing those four rows into one product would be wrong.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    private function groupBySuffix(Collection $products): Collection
    {
        return $products
            ->map(function (Product $product) {
                $en = $this->split((string) $product->name_en);
                $ar = $this->split((string) $product->name_ar);
                $kind = $en['kind'] ?? $ar['kind'];

                if ($kind === null) {
                    return null;
                }

                $label = $en['base'] !== '' ? $en['base'] : $ar['base'];

                return [
                    'product' => $product,
                    'kind' => $kind,
                    'base_ar' => $ar['base'],
                    'base_en' => trim((string) $product->name_en) === '' ? null : $en['base'],
                    'base_label' => $label,
                    'key' => $product->category_id.'|'.mb_strtolower($label),
                ];
            })
            ->filter()
            ->groupBy('key');
    }

    /**
     * Split "Sukkari 500g - Carton" into its base name and which half it is.
     * Returns kind = null when the name carries no packaging suffix at all.
     *
     * @return array{base: string, kind: string|null}
     */
    private function split(string $name): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        // Anchored to a trailing " - <suffix>" so a product whose NAME merely
        // contains the word (a gift box, say) is never treated as a carton half.
        if (! preg_match('/^(.*?)\s*[-–]\s*(\S+)$/u', $name, $m)) {
            return ['base' => $name, 'kind' => null];
        }

        $suffix = mb_strtolower($m[2]);

        return match (true) {
            in_array($suffix, self::CARTON_SUFFIXES, true) => ['base' => trim($m[1]), 'kind' => 'carton'],
            in_array($suffix, self::SINGLE_SUFFIXES, true) => ['base' => trim($m[1]), 'kind' => 'single'],
            default => ['base' => $name, 'kind' => null],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $pairs
     */
    private function renderPairs(array $pairs): void
    {
        if ($pairs === []) {
            return;
        }

        $this->info(count($pairs).' pair(s) to merge — the single survives, the carton becomes a Box option:');
        $this->table(
            ['Keep', 'Product', 'Unit', 'Box', 'Units/box', 'Absorbs (→301)'],
            array_map(fn ($p) => [
                $p['single']->sku,
                mb_substr((string) ($p['base_en'] ?? $p['base_ar']), 0, 38),
                number_format((float) $p['single']->price, 2),
                number_format((float) $p['carton']->price, 2),
                $p['units'].($this->isClean($p['ratio']) ? '' : ' (?)'),
                $p['carton']->sku,
            ], $pairs),
        );

        $unclear = array_filter($pairs, fn ($p) => ! $this->isClean($p['ratio']));

        if ($unclear === []) {
            return;
        }

        $this->warn(count($unclear).' box price(s) are not a whole multiple of the unit price, so units/box is a guess:');
        foreach ($unclear as $p) {
            $this->line(sprintf(
                '  %s  %s / %s = %s -> rounded to %d units per box',
                $p['single']->sku,
                number_format((float) $p['carton']->price, 2),
                number_format((float) $p['single']->price, 2),
                round($p['ratio'], 3),
                $p['units'],
            ));
        }
        $this->line('  Correct these on the product page — a box sale deducts this many units from stock.');
    }

    /**
     * @param  list<array<string, mixed>>  $orphans
     */
    private function renderOrphans(array $orphans): void
    {
        if ($orphans === []) {
            return;
        }

        $this->newLine();
        $this->info(count($orphans).' unpaired product(s) — only the leftover suffix is removed:');
        $this->table(
            ['SKU', 'Was', 'Becomes', 'Price', 'Live'],
            array_map(fn ($o) => [
                $o['product']->sku,
                mb_substr((string) ($o['product']->name_en ?: $o['product']->name_ar), 0, 40),
                mb_substr((string) ($o['base_en'] ?? $o['base_ar']), 0, 34),
                number_format((float) $o['product']->price, 2),
                $o['product']->is_active ? 'LIVE' : '',
            ], $orphans),
        );
        $this->warn('These keep a carton-scale price with no unit price to compare against — set a unit price and a Box option by hand before publishing them.');
    }

    /** Whether a carton / unit ratio lands close enough to a whole number to trust. */
    private function isClean(float $ratio): bool
    {
        return $ratio > 0 && abs($ratio - round($ratio)) < 0.01;
    }
}
