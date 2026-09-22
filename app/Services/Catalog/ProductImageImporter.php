<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Media;

/**
 * Replaces a product's images with better client-supplied photos from local
 * files. Deletes the product's current images (files + rows) first, then stores
 * the new set in order (first = primary) through the Media layer.
 *
 * Files are copied to an ASCII temp path before storing so Arabic source names
 * (as delivered by the client) never reach UploadedFile/realpath on Windows.
 */
class ProductImageImporter
{
    /**
     * @param  list<string>  $files  Absolute source paths, already ordered.
     * @return int Number of images stored.
     */
    public function replaceForProduct(Product $product, array $files): int
    {
        /*
         * ⚠️ forceDelete + an immediate file delete, deliberately NOT the deferred
         * path the admin panel uses. `catalog:import-images` is an operator
         * replacing a product's photography wholesale, with a --dry-run to check
         * it first — the wipe IS the request. Soft-deleting here would leave a
         * trashed row per image for every re-run of a 163-image import, and each
         * one would pin its file in R2 for the retention window.
         */
        foreach ($product->images()->get() as $image) {
            Media::delete($image->path);
        }
        $product->images()->forceDelete();

        $stored = 0;
        foreach ($files as $absolutePath) {
            $bytes = @file_get_contents($absolutePath);
            if ($bytes === false) {
                continue;
            }

            $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) ?: 'jpg';
            $tmp = tempnam(sys_get_temp_dir(), 'pimg');
            file_put_contents($tmp, $bytes);

            try {
                $path = Media::storeImageFromFile($tmp, 'image.'.$ext, "products/{$product->id}");
            } finally {
                @unlink($tmp);
            }

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'sort_order' => $stored,
                'is_primary' => $stored === 0,
            ]);
            $stored++;
        }

        return $stored;
    }
}
