<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\ImageVariants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * media:push moves stored media onto durable storage (local public disk -> R2).
 * It runs against PRODUCTION data with the container filesystem already empty,
 * so the guarantees that matter are: keys are preserved byte-for-byte, re-running
 * is safe, and it refuses to claim success while an image the storefront needs
 * is still missing.
 */
class MediaPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_copies_files_and_skips_dotfiles(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        Storage::disk('public')->put('products/1/photo.jpg', 'image-bytes');
        Storage::disk('public')->put('returns/9/proof.png', 'other-bytes');
        Storage::disk('public')->put('.gitignore', '*');

        $this->artisan('media:push --from=public --to=r2')->assertSuccessful();

        // Keys must survive untouched — product_images.path already points at them.
        Storage::disk('r2')->assertExists('products/1/photo.jpg');
        Storage::disk('r2')->assertExists('returns/9/proof.png');
        $this->assertSame('image-bytes', Storage::disk('r2')->get('products/1/photo.jpg'));

        Storage::disk('r2')->assertMissing('.gitignore');
    }

    public function test_dry_run_writes_nothing(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        Storage::disk('public')->put('products/1/photo.jpg', 'image-bytes');

        $this->artisan('media:push --from=public --to=r2 --dry-run')->assertSuccessful();

        Storage::disk('r2')->assertMissing('products/1/photo.jpg');
    }

    public function test_it_is_idempotent_and_resumes(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        Storage::disk('public')->put('products/1/a.jpg', 'a');
        Storage::disk('public')->put('products/1/b.jpg', 'b');

        // Simulate an interrupted transfer: one object already landed.
        Storage::disk('r2')->put('products/1/a.jpg', 'a');

        // NOTE: each expectsOutputToContain() is matched against a SEPARATE write
        // call, so two substrings from the same summary line can never both pass.
        $this->artisan('media:push --from=public --to=r2')
            ->expectsOutputToContain('skipped 1 already present')
            ->assertSuccessful();

        Storage::disk('r2')->assertExists('products/1/b.jpg');
        $this->assertSame('b', Storage::disk('r2')->get('products/1/b.jpg'));
    }

    public function test_it_refuses_to_copy_a_disk_onto_itself(): void
    {
        $this->artisan('media:push --from=public --to=public')->assertFailed();
    }

    public function test_verification_passes_when_every_image_and_variant_landed(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        $path = $this->productImage();
        Storage::disk('public')->put($path, 'original');
        foreach (ImageVariants::names() as $variant) {
            Storage::disk('public')->put(ImageVariants::variantPath($path, $variant), 'variant');
        }

        $this->artisan('media:push --from=public --to=r2')
            ->expectsOutputToContain('Verified')
            ->assertSuccessful();
    }

    public function test_verification_fails_when_a_variant_is_missing(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        // The original is present but its variants were never generated, which is
        // exactly the state that renders broken cards on the catalogue grid.
        $path = $this->productImage();
        Storage::disk('public')->put($path, 'original');

        $this->artisan('media:push --from=public --to=r2')
            ->expectsOutputToContain('Verification failed')
            ->assertFailed();
    }

    /** Creates a product with one image row and returns that row's path. */
    private function productImage(): string
    {
        $category = Category::create(['slug' => 'dates', 'name_ar' => 'تمور', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name_ar' => 'سكري', 'slug' => 'sukkari',
            'sku' => 'RTB-0001', 'price' => 50, 'stock' => 10, 'is_active' => true,
        ]);

        $path = "products/{$product->id}/9f8e7d6c.jpg";
        ProductImage::create(['product_id' => $product->id, 'path' => $path, 'is_primary' => true]);

        return $path;
    }
}
