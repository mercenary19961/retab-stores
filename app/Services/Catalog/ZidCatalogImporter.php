<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * One-off migration importer for the Retab catalogue exported from Zid and
 * cleaned into database/data/retab-products.csv (see CLAUDE.md → Zid migration).
 *
 * Idempotent: products are matched by their (Zid) slug via updateOrCreate, so a
 * re-run updates in place rather than duplicating. Images are only fetched when a
 * product has none yet, so re-runs don't re-download.
 *
 * Decisions baked in (confirmed with the client, 2026-07-20):
 *  - Drafts (published=no) import HIDDEN (is_active=false) so nothing is lost.
 *  - Blank ("unlimited") Zid stock → a sellable buffer of 100; SMACC re-baselines.
 *  - Uncategorised rows default to التمور (Dates).
 *  - Images are re-hosted off Zid's dying CDN through the Media layer.
 */
class ZidCatalogImporter
{
    /** Blank Zid quantity ("unlimited") → a sellable buffer until the first SMACC sync. */
    public const BLANK_STOCK_DEFAULT = 100;

    /** Uncategorised rows land here. */
    private const DEFAULT_CATEGORY = 'التمور';

    /** Nav parent groups that the leaf categories hang under (drives the navbar). */
    private const PARENTS = [
        'cat-dates' => ['name_ar' => 'التمور', 'name_en' => 'Dates'],
        'cat-gifts' => ['name_ar' => 'الهدايا', 'name_en' => 'Gifts'],
    ];

    /**
     * Zid category name_ar → [slug, name_en, parent key, homepage tile image].
     * The client can re-file/rename freely in admin afterwards.
     *
     * @var array<string, array{0:string,1:string,2:string,3:?string}>
     */
    private const CATEGORY_MAP = [
        'التمور' => ['dates', 'Dates', 'cat-dates', 'sukkari.webp'],
        'التمور المحشية' => ['stuffed-dates', 'Stuffed Dates', 'cat-dates', 'stuffed-dates.webp'],
        'الشوابير' => ['rusks', 'Rusks & Toast', 'cat-dates', null],
        'البوكسات' => ['boxes', 'Gift Boxes', 'cat-gifts', 'boxes.webp'],
        'هدايا المناسبات' => ['occasion-gifts', 'Occasion Gifts', 'cat-gifts', 'occasion-gifts.webp'],
        'منتجات متنوعة' => ['assorted', 'Assorted Products', 'cat-gifts', null],
    ];

    /**
     * Import the whole sheet. Pass $withImages=false to skip the network fetch
     * (fast; used by tests and the --no-images flag).
     *
     * @param  callable(Product):void|null  $onProgress  Called after each product.
     */
    public function import(string $csvPath, bool $withImages = true, ?callable $onProgress = null): ZidImportResult
    {
        $result = new ZidImportResult();
        $rows = $this->parseCsv($csvPath);
        $categories = $this->ensureCategories();

        // Track values chosen this run so a stray duplicate can't break the unique
        // constraints (slug / smacc_sku).
        $usedSlug = [];
        $usedSmacc = [];
        $skuSeq = 0;

        foreach ($rows as $row) {
            $catName = trim($row['category']) !== '' ? trim($row['category']) : self::DEFAULT_CATEGORY;
            $map = self::CATEGORY_MAP[$catName] ?? self::CATEGORY_MAP[self::DEFAULT_CATEGORY];
            $categoryId = $categories[$map[0]]->id;

            $slug = $this->dedupe(trim($row['old_zid_slug']) ?: 'product-' . $row['row_ref'], $usedSlug);
            // Our own product code (RTB-0001…), assigned in sheet order. The Zid SKU
            // (Z.30547.*) is junk, unrelated to SMACC, so it is NOT stored at all.
            $sku = 'RTB-' . str_pad((string) ++$skuSeq, 4, '0', STR_PAD_LEFT);

            // smacc_sku is a nullable UNIQUE key: keep only the first occurrence.
            $smacc = trim($row['smacc_sku']);
            if ($smacc === '' || isset($usedSmacc[$smacc])) {
                $smacc = null;
            } else {
                $usedSmacc[$smacc] = true;
            }

            $qtyRaw = trim($row['quantity']);
            $stock = $qtyRaw === '' ? self::BLANK_STOCK_DEFAULT : max(0, (int) $qtyRaw);

            $active = strtolower(trim($row['published'])) === 'yes';
            $desc = trim($row['short_description_ar']);
            $sale = trim($row['sale_price']) !== '' ? (float) $row['sale_price'] : null;

            $attributes = [
                'category_id' => $categoryId,
                'name_ar' => trim($row['name_ar']),
                'name_en' => trim($row['name_en']) ?: null,
                'price' => (float) $row['price'],
                'sale_price' => $sale,
                'sku' => $sku,
                'smacc_sku' => $smacc,
                'stock' => $stock,
                'is_active' => $active,
                'is_featured' => false,
            ];

            // Only touch descriptions when the sheet actually has one, so a re-run
            // never wipes copy added by hand in admin (the Zid export has none for
            // most rows). Omitting the keys leaves the existing value on update.
            if ($desc !== '') {
                $attributes['description_ar'] = $desc;
                $attributes['short_description_ar'] = mb_substr($desc, 0, 500);
            }

            $product = Product::updateOrCreate(['slug' => $slug], $attributes);

            $result->productsSaved++;
            if (! $active) {
                $result->drafts++;
            }

            if ($withImages && $product->images()->count() === 0) {
                $this->importImages($row['images'], $product, $result);
            }

            if ($onProgress) {
                $onProgress($product);
            }
        }

        return $result;
    }

    /** Download each image URL (newline-separated) and attach it; first = primary. */
    private function importImages(string $imagesCell, Product $product, ZidImportResult $result): void
    {
        $urls = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $imagesCell))));

        foreach ($urls as $i => $url) {
            $path = $this->downloadImage($url, $product->id, $result);
            if ($path === null) {
                continue;
            }

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'sort_order' => $i,
                'is_primary' => $i === 0,
            ]);
            $result->imagesSaved++;
        }
    }

    private function downloadImage(string $url, int $productId, ZidImportResult $result): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'RetabCatalogImporter/1.0'])
                ->timeout(20)
                ->get($url);

            if (! $response->successful()) {
                $result->imagesFailed++;
                Log::warning('Zid image fetch: non-2xx', ['url' => $url, 'status' => $response->status()]);

                return null;
            }

            $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
            $tmp = tempnam(sys_get_temp_dir(), 'zidimg');
            file_put_contents($tmp, $response->body());

            try {
                // Runs through the Media layer's extension + MIME validation.
                return Media::storeImageFromFile($tmp, 'image.' . $ext, "products/{$productId}");
            } finally {
                @unlink($tmp);
            }
        } catch (\Throwable $e) {
            $result->imagesFailed++;
            Log::warning('Zid image fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Ensure the two nav parents + six leaf categories exist; returns leaves by slug. */
    private function ensureCategories(): array
    {
        $parents = [];
        $i = 0;
        foreach (self::PARENTS as $slug => $data) {
            $parents[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                $data + ['parent_id' => null, 'sort_order' => $i++, 'is_active' => true],
            );
        }

        $leaves = [];
        $j = 0;
        foreach (self::CATEGORY_MAP as $nameAr => [$slug, $nameEn, $parentKey, $image]) {
            $leaves[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'parent_id' => $parents[$parentKey]->id,
                    'image' => $image ? '/images/categories/' . $image : null,
                    'sort_order' => $j++,
                    'is_active' => true,
                ],
            );
        }

        return $leaves;
    }

    /**
     * Read the cleaned CSV (fgetcsv handles the quoted, multi-line image cells).
     *
     * @return array<int, array<string, string>>
     */
    private function parseCsv(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Catalogue CSV not found: {$path}");
        }

        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);

            throw new RuntimeException("Catalogue CSV is empty: {$path}");
        }

        // Strip a UTF-8 BOM from the first header cell if present.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $count = count($header);

        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            if (count($r) === 1 && ($r[0] === null || trim((string) $r[0]) === '')) {
                continue; // blank line
            }
            $r = array_pad(array_slice($r, 0, $count), $count, '');
            $rows[] = array_combine($header, array_map(fn ($v) => (string) $v, $r));
        }
        fclose($fh);

        return $rows;
    }

    /** Return $value, suffixing -2, -3, … until it's unused; records the result. */
    private function dedupe(string $value, array &$used): string
    {
        $candidate = $value;
        $n = 1;
        while (isset($used[$candidate])) {
            $n++;
            $candidate = $value . '-' . $n;
        }
        $used[$candidate] = true;

        return $candidate;
    }
}
