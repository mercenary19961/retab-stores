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
        foreach ($product->images()->get() as $image) {
            Media::delete($image->path);
        }
        $product->images()->delete();

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
                $path = Media::storeImageFromFile($tmp, 'image.' . $ext, "products/{$product->id}");
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
