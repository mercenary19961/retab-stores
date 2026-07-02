<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
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
            'sku' => 'SK-' . uniqid(),
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

        $this->assertDatabaseMissing('product_images', ['id' => $primary->id]);
        $this->assertTrue($other->fresh()->is_primary); // promoted
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
