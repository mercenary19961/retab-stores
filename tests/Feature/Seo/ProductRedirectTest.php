<?php

namespace Tests\Feature\Seo;

use App\Models\Category;
use App\Models\Product;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'is_active' => true]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name_ar' => 'تمر سكري',
            'slug' => 'sukkari',
            'sku' => 'SKU-'.fake()->unique()->numerify('####'),
            'price' => 50,
            'stock' => 10,
            'is_active' => true,
        ], $overrides));
    }

    /** GET the storefront product URL for a (possibly Arabic) slug. */
    private function getProduct(string $slug)
    {
        return $this->get('/products/'.rawurlencode($slug));
    }

    // ---- Route: ->missing() redirect resolution -------------------------------

    public function test_old_slug_301s_to_current_product_url(): void
    {
        $product = $this->makeProduct(['slug' => 'خلاص-درجة-أولى-بالشمر']);
        Redirect::create(['from_slug' => 'Khalas-with-fennel', 'product_id' => $product->id]);

        $response = $this->getProduct('Khalas-with-fennel');

        $response->assertStatus(301);
        $response->assertRedirect(route('shop.product', $product->slug));
    }

    public function test_redirect_follows_the_products_current_slug(): void
    {
        // The redirect targets the product, not a frozen URL — a later re-slug follows.
        $product = $this->makeProduct(['slug' => 'first-slug']);
        Redirect::create(['from_slug' => 'legacy', 'product_id' => $product->id]);

        $product->update(['slug' => 'renamed-slug']);

        $this->getProduct('legacy')->assertRedirect(route('shop.product', 'renamed-slug'));
    }

    public function test_unknown_slug_without_a_redirect_404s(): void
    {
        $this->getProduct('no-such-thing')->assertNotFound();
    }

    public function test_live_product_still_renders_200(): void
    {
        $this->makeProduct(['slug' => 'live-one']);

        $this->getProduct('live-one')->assertOk();
    }

    public function test_redirect_hit_is_counted(): void
    {
        $product = $this->makeProduct(['slug' => 'target']);
        $redirect = Redirect::create(['from_slug' => 'old', 'product_id' => $product->id]);

        $this->getProduct('old');

        $this->assertSame(1, $redirect->fresh()->hits);
    }

    public function test_redirect_to_a_hidden_product_without_fallback_404s(): void
    {
        $product = $this->makeProduct(['slug' => 'gone', 'is_active' => false, 'is_coming_soon' => false]);
        Redirect::create(['from_slug' => 'old-gone', 'product_id' => $product->id]);

        $this->getProduct('old-gone')->assertNotFound();
    }

    public function test_static_to_url_redirect_without_a_product(): void
    {
        Redirect::create(['from_slug' => 'legacy-category', 'to_url' => '/shop']);

        $this->getProduct('legacy-category')->assertRedirect('/shop');
    }

    // ---- Command: catalog:clean-slugs -----------------------------------------

    public function test_command_normalises_junk_slug_and_records_a_redirect(): void
    {
        $product = $this->makeProduct(['slug' => 'ar-تمر-ذهبي', 'name_ar' => 'تمر ذهبي']);

        $this->artisan('catalog:clean-slugs --apply')->assertSuccessful();

        $this->assertSame('تمر-ذهبي', $product->fresh()->slug);
        $this->assertDatabaseHas('redirects', [
            'from_slug' => 'ar-تمر-ذهبي',
            'product_id' => $product->id,
            'status' => 301,
        ]);
        // And the old URL now resolves through the map.
        $this->getProduct('ar-تمر-ذهبي')->assertRedirect(route('shop.product', 'تمر-ذهبي'));
    }

    public function test_command_leaves_clean_arabic_slugs_untouched(): void
    {
        $product = $this->makeProduct(['slug' => 'تمر-سكري-فاخر', 'name_ar' => 'تمر سكري فاخر']);

        $this->artisan('catalog:clean-slugs --apply')->assertSuccessful();

        $this->assertSame('تمر-سكري-فاخر', $product->fresh()->slug);
        $this->assertDatabaseCount('redirects', 0);
    }

    public function test_command_dry_run_writes_nothing(): void
    {
        $product = $this->makeProduct(['slug' => 'RETAB076', 'name_ar' => 'بوكس مكسرات']);

        $this->artisan('catalog:clean-slugs')->assertSuccessful();

        $this->assertSame('RETAB076', $product->fresh()->slug);
        $this->assertDatabaseCount('redirects', 0);
    }

    public function test_command_is_idempotent(): void
    {
        $this->makeProduct(['slug' => 'Najdi-coffee', 'name_ar' => 'قهوة نجدية']);

        $this->artisan('catalog:clean-slugs --apply')->assertSuccessful();
        $this->artisan('catalog:clean-slugs --apply')->assertSuccessful();

        $this->assertDatabaseCount('redirects', 1);
    }

    public function test_command_dedupes_colliding_new_slugs(): void
    {
        $a = $this->makeProduct(['slug' => 'ar-box-one', 'name_ar' => 'بوكس تمر']);
        $b = $this->makeProduct(['slug' => 'ar-box-two', 'name_ar' => 'بوكس تمر']);

        $this->artisan('catalog:clean-slugs --apply')->assertSuccessful();

        $slugs = [$a->fresh()->slug, $b->fresh()->slug];
        sort($slugs);
        $this->assertSame(['بوكس-تمر', 'بوكس-تمر-2'], $slugs);
    }
}
