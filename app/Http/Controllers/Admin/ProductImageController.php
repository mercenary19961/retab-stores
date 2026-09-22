<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ChangeLog\ChangeLogService;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Product image management — separate from the product text form so uploads use a
 * clean multipart POST (no PUT-multipart quirks). All file writes go through
 * {@see Media}. The first image of a product becomes its primary automatically.
 *
 * 🔑 Images are logged AGAINST THE IMAGE, not against the product, even though
 * they belong to one. A ProductImage row carries the path, the primary flag and
 * the sort order — everything a revert needs — whereas folding the change into
 * the product's own entry would mean recording a field the products table does
 * not have, which the revert machinery would silently drop. Both file under the
 * `products` section key, so the Products page's "undo last save" still points
 * at an image change made from the same screen.
 */
class ProductImageController extends Controller
{
    public function store(Request $request, Product $product, ChangeLogService $changeLog)
    {
        $request->validate([
            'images' => ['required', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $nextSort = (int) $product->images()->max('sort_order');

        foreach ($request->file('images') as $file) {
            try {
                $path = Media::storeImage($file, "products/{$product->id}");
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }

            DB::transaction(function () use ($product, $path, &$nextSort, &$hasPrimary, $changeLog) {
                $image = $product->images()->create([
                    'path' => $path,
                    'sort_order' => ++$nextSort,
                    'is_primary' => ! $hasPrimary,
                ]);

                $changeLog->logCreated($image, $this->label($product, $image));
            });

            $hasPrimary = true; // only the very first image is auto-primary
        }

        return back()->with('success', __('messages.admin.images_uploaded'));
    }

    public function destroy(Product $product, ProductImage $image, ChangeLogService $changeLog)
    {
        abort_unless($image->product_id === $product->id, 404);

        $wasPrimary = $image->is_primary;

        /*
         * 🔴 THE FILE DELIBERATELY STAYS. This used to Media::delete() the path
         * before dropping the row, so removing the wrong photo meant the original
         * was gone from R2 within the second and the client had to find it again.
         * The row soft-deletes now, so Undo restores the image with its file
         * still in place; media:purge-trash removes it once the retention window
         * has closed. See App\Support\MediaTrash.
         */
        DB::transaction(function () use ($product, $image, $wasPrimary, $changeLog) {
            $changeLog->logDeleted($image, $this->label($product, $image));
            $image->delete();

            // Promote another image to primary if we removed the primary one.
            if ($wasPrimary) {
                $next = $product->images()->orderBy('sort_order')->first();
                $next?->update(['is_primary' => true]);
            }
        });

        // 🔑 Images live on their own endpoint, so removing the last one leaves
        // the product row untouched and nothing would otherwise re-evaluate it —
        // a live product would keep selling with no picture. Re-check, and say
        // so plainly if that just pulled it off the storefront.
        $product->unsetRelation('images');
        if ($product->syncPublishability()) {
            return back()->with('error', __('messages.admin.product_hidden_no_image'));
        }

        return back()->with('success', __('messages.admin.image_deleted'));
    }

    public function setPrimary(Product $product, ProductImage $image, ChangeLogService $changeLog)
    {
        abort_unless($image->product_id === $product->id, 404);

        // Already the primary — nothing to write and nothing worth logging.
        if ($image->is_primary) {
            return back()->with('success', __('messages.admin.primary_image_set'));
        }

        DB::transaction(function () use ($product, $image, $changeLog) {
            $before = $image->attributesToArray();

            $product->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);

            // ⚠️ Only the NEW primary is logged, though the demotion writes every
            // other row. Reverting this entry sets is_primary back to false on
            // this image, which leaves the product with none — so the undo is
            // honest but partial, and the product's own publish guard does not
            // depend on which image is primary.
            $changeLog->logUpdated($image, $before, $this->label($product, $image));
        });

        return back()->with('success', __('messages.admin.primary_image_set'));
    }

    /** What the change log calls an image: its product, plus which one. */
    private function label(Product $product, ProductImage $image): string
    {
        return $product->name_ar.' — image #'.$image->getKey();
    }
}
