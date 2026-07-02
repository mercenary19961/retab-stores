<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Media;
use Illuminate\Http\Request;

/**
 * Product image management — separate from the product text form so uploads use a
 * clean multipart POST (no PUT-multipart quirks). All file writes go through
 * {@see Media}. The first image of a product becomes its primary automatically.
 */
class ProductImageController extends Controller
{
    public function store(Request $request, Product $product)
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

            $product->images()->create([
                'path' => $path,
                'sort_order' => ++$nextSort,
                'is_primary' => ! $hasPrimary,
            ]);

            $hasPrimary = true; // only the very first image is auto-primary
        }

        return back()->with('success', __('messages.admin.images_uploaded'));
    }

    public function destroy(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);

        Media::delete($image->path);
        $wasPrimary = $image->is_primary;
        $image->delete();

        // Promote another image to primary if we removed the primary one.
        if ($wasPrimary) {
            $next = $product->images()->orderBy('sort_order')->first();
            $next?->update(['is_primary' => true]);
        }

        return back()->with('success', __('messages.admin.image_deleted'));
    }

    public function setPrimary(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);

        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return back()->with('success', __('messages.admin.primary_image_set'));
    }
}
