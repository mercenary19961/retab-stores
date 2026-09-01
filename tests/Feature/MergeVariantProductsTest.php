<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * catalog:merge-variants — folding the Zid import's flattened "- Carton" /
 * "- Single" product pairs back into one product with a Box option.
 */
class MergeVariantProductsTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name_ar' => 'تمور فاخرة', 'slug' => 'dates']);
    }

    private function product(string $sku, string $nameEn, string $nameAr, float $price, int $stock = 100, ?Category $category = null): Product
    {
        return Product::create([
            'category_id' => ($category ?? $this->category)->id,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'slug' => 'slug-'.$sku,
            'price' => $price,
            'sku' => $sku,
            'stock' => $stock,
        ]);
    }

    private function pair(string $base, string $baseAr, float $unit, float $box, ?Category $category = null): array
    {
        return [
            $this->product('S-'.$base, $base.' - Single', $baseAr.' - حبة', $unit, 144, $category),
            $this->product('C-'.$base, $base.' - Carton', $baseAr.' - كرتون', $box, 12, $category),
        ];
    }

    public function test_a_pair_becomes_one_product_with_a_box_option(): void
    {
        [$single, $carton] = $this->pair('Sukkari 500g', 'سكري 500جم', 11.50, 138.00);

        $this->artisan('catalog:merge-variants --apply')->assertSuccessful();

        $single->refresh();

        // The suffix comes off both names; the survivor keeps its own price,
        // stock and slug.
        $this->assertSame('Sukkari 500g', $single->name_en);
        $this->assertSame('سكري 500جم', $single->name_ar);
        $this->assertSame('11.50', $single->price);
        $this->assertSame(144, $single->stock);
        $this->assertSame('slug-S-Sukkari 500g', $single->slug);

        $option = $single->options()->sole();
        $this->assertTrue($option->is_box);
        $this->assertSame('138.00', $option->price);
        $this->assertNull($option->amount);
        // A box has no weight to scale from, so its price must never be
        // recomputed by the editor's auto-scaling.
        $this->assertTrue($option->price_overridden);
        // 138 / 11.50 = 12 units per box.
        $this->assertSame(12, $option->stock_units);

        // The carton row is gone from the catalogue but recoverable.
        $this->assertSoftDeleted('products', ['id' => $carton->id]);
    }

    public function test_the_absorbed_carton_url_redirects_to_the_survivor(): void
    {
        [$single, $carton] = $this->pair('Sukkari 500g', 'سكري 500جم', 11.50, 138.00);

        $this->artisan('catalog:merge-variants --apply')->assertSuccessful();

        $redirect = Redirect::where('from_slug', $carton->slug)->sole();
        $this->assertSame($single->id, $redirect->product_id);
        $this->assertSame(301, $redirect->status);

        // And it actually serves: an indexed carton URL lands on the merged
        // product rather than 404ing. Needs the survivor visible, which needs an
        // image for the publish guard to allow.
        ProductImage::create(['product_id' => $single->id, 'path' => 'products/x.jpg', 'is_primary' => true]);
        $single->update(['is_active' => true]);

        $this->get(route('shop.product', $carton->slug))
            ->assertRedirect(route('shop.product', $single->fresh()->slug));
    }

    public function test_a_box_price_that_is_not_a_whole_multiple_is_rounded_and_reported(): void
    {
        // The real catalogue's Khalas Sajir: 184 / 24 = 7.667.
        $this->pair('Khalas Sajir 1kg', 'خلاص ساجر كيلو', 24.00, 184.00);

        $this->artisan('catalog:merge-variants --apply')
            ->expectsOutputToContain('not a whole multiple')
            ->assertSuccessful();

        $this->assertSame(8, Product::where('sku', 'S-Khalas Sajir 1kg')->sole()->options()->sole()->stock_units);
    }

    public function test_the_same_base_name_in_two_categories_stays_two_products(): void
    {
        // 🔴 The trap this guards: "Khalas Ushaiger Grade 1 250g" is a real pair in
        // BOTH Premium Dates and Assorted Products at different prices. Grouping on
        // the name alone would collapse four rows into one product.
        $other = Category::create(['name_ar' => 'متنوعة', 'slug' => 'assorted']);
        $this->pair('Ushaiger 250g', 'أشيقر 250جم', 5.75, 69.00);
        $this->pair('Ushaiger 250g B', 'أشيقر 250جم', 5.00, 120.00, $other);
        // Give the second pair the same English base as the first.
        Product::where('sku', 'S-Ushaiger 250g B')->update(['name_en' => 'Ushaiger 250g - Single']);
        Product::where('sku', 'C-Ushaiger 250g B')->update(['name_en' => 'Ushaiger 250g - Carton']);

        $this->artisan('catalog:merge-variants --apply')->assertSuccessful();

        $this->assertSame(12, Product::where('sku', 'S-Ushaiger 250g')->sole()->options()->sole()->stock_units);
        $this->assertSame(24, Product::where('sku', 'S-Ushaiger 250g B')->sole()->options()->sole()->stock_units);
        $this->assertSame(2, Product::whereNull('deleted_at')->count());
    }

    public function test_an_unpaired_carton_is_only_renamed(): void
    {
        $orphan = $this->product('C-Munify', 'Premium Munify 250g - Carton', 'منيفي فاخر 250جم - كرتون', 57.50, 7);

        $this->artisan('catalog:merge-variants --apply')->assertSuccessful();

        $orphan->refresh();
        $this->assertSame('Premium Munify 250g', $orphan->name_en);
        $this->assertSame('منيفي فاخر 250جم', $orphan->name_ar);
        // Nothing is invented: a carton-priced product with no known unit price
        // keeps its price and stock, and gains no option.
        $this->assertSame('57.50', $orphan->price);
        $this->assertSame(7, $orphan->stock);
        $this->assertSame(0, $orphan->options()->count());
        $this->assertDatabaseCount('redirects', 0);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        [$single, $carton] = $this->pair('Sukkari 500g', 'سكري 500جم', 11.50, 138.00);

        $this->artisan('catalog:merge-variants')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame('Sukkari 500g - Single', $single->fresh()->name_en);
        $this->assertNotNull(Product::find($carton->id));
        $this->assertDatabaseCount('product_options', 0);
        $this->assertDatabaseCount('redirects', 0);
    }

    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        $this->pair('Sukkari 500g', 'سكري 500جم', 11.50, 138.00);

        $this->artisan('catalog:merge-variants --apply')->assertSuccessful();
        $this->artisan('catalog:merge-variants --apply')
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();

        $this->assertDatabaseCount('product_options', 1);
        $this->assertDatabaseCount('redirects', 1);
    }

    public function test_a_product_merely_containing_the_word_box_is_left_alone(): void
    {
        // Only a trailing " - <suffix>" marks a flattened half; a gift box is a
        // product in its own right.
        $gift = $this->product('G-1', 'Luxury Gift Box', 'بوكس هدايا فاخر', 250.00);

        $this->artisan('catalog:merge-variants --apply')
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();

        $this->assertSame('Luxury Gift Box', $gift->fresh()->name_en);
    }
}
