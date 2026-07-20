<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductImageImporter;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageImporterTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::create(['name_ar' => 'ت', 'slug' => 'c-' . uniqid()]);

        return Product::create([
            'category_id' => $category->id, 'name_ar' => 'م', 'slug' => 'p-' . uniqid(),
            'price' => 10, 'sku' => 'SK-' . uniqid(), 'stock' => 5, 'is_active' => true,
        ]);
    }

    /** A valid 1×1 PNG on disk (passes Media's extension + MIME checks). */
    private function tmpPng(): string
    {
        $path = sys_get_temp_dir() . '/pi_' . uniqid() . '.png';
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ));

        return $path;
    }

    public function test_it_replaces_existing_images_first_is_primary(): void
    {
        Storage::fake(Media::disk());
        $product = $this->product();

        // An existing (stale) image that must be cleared out.
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/old.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $files = [$this->tmpPng(), $this->tmpPng(), $this->tmpPng()];
        $count = app(ProductImageImporter::class)->replaceForProduct($product, $files);
        foreach ($files as $f) {
            @unlink($f);
        }

        $this->assertSame(3, $count);

        $images = $product->images()->orderBy('sort_order')->get();
        $this->assertCount(3, $images);                 // old one replaced, not appended
        $this->assertTrue($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);
        $this->assertDatabaseMissing('product_images', ['path' => 'products/old.jpg']);

        // The stored files actually landed on the media disk.
        Storage::disk(Media::disk())->assertExists($images[0]->path);
    }
}
