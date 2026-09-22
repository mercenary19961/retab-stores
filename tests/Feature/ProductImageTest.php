<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ChangeLog\ChangeLogService;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function product(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'slug' => 'sukkari',
            'price' => 50,
            'sku' => 'SK-'.uniqid(),
            'stock' => 10,
            'is_active' => true,
        ]);
    }

    public function test_media_helper_rejects_non_images(): void
    {
        Storage::fake('public');
        $this->expectException(\RuntimeException::class);

        Media::storeImage(UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'), 'products/1');
    }

    public function test_media_helper_stores_image_with_random_name(): void
    {
        Storage::fake('public');

        $path = Media::storeImage(UploadedFile::fake()->image('photo.jpg'), 'products/1');

        $this->assertStringStartsWith('products/1/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_first_uploaded_image_becomes_primary(): void
    {
        Storage::fake('public');
        $product = $this->product();

        $this->actingAs($this->staff())
            ->post("/admin/products/{$product->id}/images", [
                'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
            ])
            ->assertRedirect();

        $this->assertSame(2, $product->images()->count());
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_delete_promotes_a_new_primary(): void
    {
        Storage::fake('public');
        $product = $this->product();
        $primary = ProductImage::create(['product_id' => $product->id, 'path' => 'products/x/a.jpg', 'sort_order' => 1, 'is_primary' => true]);
        $other = ProductImage::create(['product_id' => $product->id, 'path' => 'products/x/b.jpg', 'sort_order' => 2, 'is_primary' => false]);

        $this->actingAs($this->staff())
            ->delete("/admin/products/{$product->id}/images/{$primary->id}")
            ->assertRedirect();

        $this->assertNull(ProductImage::find($primary->id), 'off the product page');
        $this->assertTrue($other->fresh()->is_primary); // promoted
    }

    /**
     * 🔴 Deleting the wrong photo used to be unrecoverable: the file was removed
     * from R2 in the same request, so the client had to find the original again.
     * The row soft-deletes and the file waits out the retention window instead.
     */
    public function test_deleting_an_image_keeps_the_file_and_can_be_undone(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $disk->put('products/x/a.jpg', 'x');

        $product = $this->product();
        $image = ProductImage::create(['product_id' => $product->id, 'path' => 'products/x/a.jpg', 'sort_order' => 1, 'is_primary' => true]);

        $this->actingAs($this->staff())
            ->delete("/admin/products/{$product->id}/images/{$image->id}")
            ->assertRedirect();

        $disk->assertExists('products/x/a.jpg');

        $log = ActivityLog::where('subject_type', ProductImage::class)
            ->where('subject_id', $image->id)
            ->where('action', ActivityLog::ACTION_DELETED)
            ->firstOrFail();

        $this->assertTrue(app(ChangeLogService::class)->revert($log)->ok);
        $this->assertSame(1, $product->images()->count());
        $this->assertSame('products/x/a.jpg', $product->images()->first()->path);
    }

    public function test_set_primary_moves_the_flag(): void
    {
        Storage::fake('public');
        $product = $this->product();
        $a = ProductImage::create(['product_id' => $product->id, 'path' => 'p/a.jpg', 'is_primary' => true]);
        $b = ProductImage::create(['product_id' => $product->id, 'path' => 'p/b.jpg', 'is_primary' => false]);

        $this->actingAs($this->staff())
            ->put("/admin/products/{$product->id}/images/{$b->id}/primary")
            ->assertRedirect();

        $this->assertFalse($a->fresh()->is_primary);
        $this->assertTrue($b->fresh()->is_primary);
    }

    public function test_storefront_product_exposes_image_urls(): void
    {
        Storage::fake('public');
        $product = $this->product();
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/1/a.jpg', 'is_primary' => true]);

        $this->get("/products/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('shop/product')->has('product.images', 1));
    }

    public function test_customers_cannot_upload_images(): void
    {
        Storage::fake('public');
        $product = $this->product();
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->post("/admin/products/{$product->id}/images", ['images' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertForbidden();
    }
}
