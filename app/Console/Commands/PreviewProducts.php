<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 🔴 TEMPORARY — delete before launch: `php artisan catalog:preview-products --remove`.
 *
 * Seeds the client-supplied photography samples as ordinary-looking catalogue
 * products so the new photo style can be judged on a real card, in situ, rather
 * than from a folder of files. They are pinned to the top of /shop and, being the
 * newest rows, also lead the homepage "new arrivals" carousel.
 *
 * Everything about them is designed to be reversible:
 *
 *  - every row carries the `PREVIEW-` SKU prefix and a `preview-` slug, so they can
 *    be found without relying on this file still existing;
 *  - `--remove` deletes the rows AND their stored files (originals + WebP variants)
 *    through the Media layer, so nothing is orphaned in R2;
 *  - `forceDelete()`, not `delete()` — Product soft-deletes, and a trashed preview
 *    row would still occupy its unique slug and SKU.
 *
 * ⚠️ These are deliberately ACTIVE and therefore BUYABLE, because a Coming-Soon
 * product renders a badge and hides its price, which is exactly the part of the
 * card being reviewed. The exposure is bounded: no order ships without an admin
 * confirming it, and the store is noindexed until launch. If a real customer ever
 * orders one, reject it in the panel like any out-of-stock item.
 */
class PreviewProducts extends Command
{
    protected $signature = 'catalog:preview-products
        {--remove : Delete the preview products and their images instead of seeding}';

    protected $description = 'Seed (or remove) the temporary photography-sample products shown at the top of the catalogue.';

    /** Marks every row this command owns. Nothing else in the catalogue may use it. */
    private const SKU_PREFIX = 'PREVIEW-';

    /** Names are prefixed so nobody mistakes a sample for real catalogue copy. */
    private const NAME_PREFIX_AR = '[عينة] ';

    private const NAME_PREFIX_EN = '[Sample] ';

    /**
     * One entry per image file, captioned to match what the photo actually shows —
     * a plausible name over the wrong product would undermine the style review.
     *
     * @var list<array{image:int, category:string, ar:string, en:string, price:float}>
     */
    private const ITEMS = [
        ['image' => 11, 'category' => 'dates', 'ar' => 'تمر سكري فاخر 1 كجم', 'en' => 'Premium Sukkari Dates 1kg', 'price' => 75.00],
        ['image' => 13, 'category' => 'dates', 'ar' => 'تمر عجوة المدينة 1 كجم', 'en' => 'Ajwa Madinah Dates 1kg', 'price' => 145.00],
        ['image' => 10, 'category' => 'dates', 'ar' => 'تمر صقعي مختار 1 كجم', 'en' => 'Selected Sagai Dates 1kg', 'price' => 89.00],
        ['image' => 7, 'category' => 'dates', 'ar' => 'تمر خلاص طازج 500 جم', 'en' => 'Fresh Khalas Dates 500g', 'price' => 42.00],
        ['image' => 1, 'category' => 'occasion-gifts', 'ar' => 'صندوق ضيافة التمور المحشية', 'en' => 'Stuffed Dates Hospitality Box', 'price' => 210.00],
        ['image' => 2, 'category' => 'occasion-gifts', 'ar' => 'علبة هدايا وردية للمناسبات', 'en' => 'Rose Occasion Gift Box', 'price' => 185.00],
        ['image' => 9, 'category' => 'occasion-gifts', 'ar' => 'طبق الضيافة السداسي', 'en' => 'Hexagonal Hospitality Tray', 'price' => 165.00],
        ['image' => 4, 'category' => 'boxes', 'ar' => 'بوكس التمور الخشبي', 'en' => 'Wooden Dates Box', 'price' => 230.00],
        ['image' => 3, 'category' => 'boxes', 'ar' => 'بوكس رطاب الأخضر المتنوع', 'en' => 'Retab Green Assorted Box', 'price' => 120.00],
        ['image' => 12, 'category' => 'boxes', 'ar' => 'بوكس رطاب الصغير', 'en' => 'Retab Petite Box', 'price' => 68.00],
        ['image' => 5, 'category' => 'rusks', 'ar' => 'شابورة الرشاقة الأصلية بالقمح', 'en' => 'Al Rashaqa Original Wheat Rusk', 'price' => 28.00],
        ['image' => 6, 'category' => 'rusks', 'ar' => 'شابورة رطاب المقرمشة', 'en' => 'Retab Crunchy Rusk', 'price' => 32.00],
        ['image' => 8, 'category' => 'assorted', 'ar' => 'معمول التمر بالمكسرات', 'en' => 'Date Maamoul with Nuts', 'price' => 55.00],
    ];

    public function handle(): int
    {
        return $this->option('remove') ? $this->remove() : $this->seed();
    }

    private function seed(): int
    {
        $dir = database_path('data/preview-products');

        // Resolved once — never a lookup per row inside the loop.
        $categories = Category::whereIn('slug', array_column(self::ITEMS, 'category'))
            ->pluck('id', 'slug');

        $created = 0;
        $skipped = 0;

        foreach (self::ITEMS as $i => $item) {
            $sku = self::SKU_PREFIX.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $file = sprintf('%s/preview-%02d.webp', $dir, $item['image']);

            if (! is_file($file)) {
                $this->warn("Missing image, skipping {$sku}: {$file}");

                continue;
            }

            $categoryId = $categories[$item['category']] ?? null;
            if (! $categoryId) {
                $this->warn("Missing category '{$item['category']}', skipping {$sku}.");

                continue;
            }

            // A second run must not re-upload: that would orphan the previous file
            // on the media disk (R2 in production), which nothing would clean up.
            if (Product::withTrashed()->where('sku', $sku)->exists()) {
                $skipped++;

                continue;
            }

            DB::transaction(function () use ($sku, $item, $categoryId, $file, &$created) {
                $product = Product::create([
                    'category_id' => $categoryId,
                    'name_ar' => self::NAME_PREFIX_AR.$item['ar'],
                    'name_en' => self::NAME_PREFIX_EN.$item['en'],
                    'slug' => 'preview-'.strtolower(str_replace(self::SKU_PREFIX, '', $sku)),
                    'description_ar' => 'منتج تجريبي لعرض أسلوب التصوير الجديد. سيتم حذفه قبل الإطلاق.',
                    'description_en' => 'Temporary product used to preview the new photography style. It will be removed before launch.',
                    'price' => $item['price'],
                    'sku' => $sku,
                    'stock' => 10,
                    'is_active' => true,
                    // Pins them above the real catalogue: /shop sorts by
                    // is_featured first, then newest.
                    'is_featured' => true,
                ]);

                $path = Media::storeImageFromFile($file, basename($file), "products/{$product->id}");

                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'alt' => $item['en'],
                    'sort_order' => 0,
                    'is_primary' => true,
                ]);

                $created++;
            });
        }

        $this->info("Preview products seeded: {$created} created, {$skipped} already present.");
        $this->line('🔴 Remove before launch: php artisan catalog:preview-products --remove');

        return self::SUCCESS;
    }

    private function remove(): int
    {
        // withTrashed: a preview row that was soft-deleted by hand still holds its
        // unique slug and SKU, so it has to be cleaned up here too.
        $products = Product::withTrashed()
            ->where('sku', 'like', self::SKU_PREFIX.'%')
            ->with('images')
            ->get();

        if ($products->isEmpty()) {
            $this->info('No preview products found — nothing to remove.');

            return self::SUCCESS;
        }

        $files = 0;

        foreach ($products as $product) {
            foreach ($product->images as $image) {
                // Through Media so the WebP variants go with the original.
                Media::delete($image->path);
                $files++;
            }

            $product->images()->delete();
            $product->forceDelete();
        }

        $this->info("Removed {$products->count()} preview products and {$files} stored images.");

        return self::SUCCESS;
    }
}
