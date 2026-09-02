<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * catalog:absorb — folding one duplicate Zid listing into the one being kept.
 */
class AbsorbProductTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name_ar' => 'تمور', 'slug' => 'dates']);
    }

    private function product(string $sku, array $attributes = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $this->category->id,
            'name_ar' => 'خلاص '.$sku,
            'slug' => 'slug-'.$sku,
            'price' => 10,
            'sku' => $sku,
            'stock' => 50,
        ], $attributes));
    }

    public function test_the_keeper_keeps_its_own_price_stock_and_options(): void
    {
        // 🔑 Which listing is current is the entire question, so nothing about the
        // commercial terms is inferred: the keeper's own values survive untouched.
        $winner = $this->product('KEEP', ['price' => 5.75, 'stock' => 100]);
        $winner->options()->create(['label_ar' => 'كرتون', 'is_box' => true, 'price' => 69, 'stock_units' => 12]);
        $loser = $this->product('DROP', ['price' => 5.00, 'stock' => 7]);
        $loser->options()->create(['label_ar' => 'كرتون', 'is_box' => true, 'price' => 120, 'stock_units' => 24]);

        $this->artisan('catalog:absorb DROP KEEP --apply')->assertSuccessful();

        $winner->refresh();
        $this->assertSame('5.75', $winner->price);
        $this->assertSame(100, $winner->stock);
        $this->assertSame(1, $winner->options()->count());
        $this->assertSame('69.00', $winner->options()->sole()->price);
        $this->assertSoftDeleted('products', ['id' => $loser->id]);
    }

    public function test_only_empty_fields_are_inherited(): void
    {
        $winner = $this->product('KEEP', ['name_en' => 'Kept name', 'description_ar' => null]);
        $this->product('DROP', ['name_en' => 'Dropped name', 'description_ar' => 'وصف مفيد', 'smacc_sku' => 'SM-9']);

        $this->artisan('catalog:absorb DROP KEEP --apply')->assertSuccessful();

        $winner->refresh();
        $this->assertSame('Kept name', $winner->name_en);   // never overwritten
        $this->assertSame('وصف مفيد', $winner->description_ar); // filled because empty
        $this->assertSame('SM-9', $winner->smacc_sku);
    }

    public function test_the_retired_slug_redirects_to_the_keeper(): void
    {
        $winner = $this->product('KEEP');
        ProductImage::create(['product_id' => $winner->id, 'path' => 'products/k.jpg', 'is_primary' => true]);
        $winner->update(['is_active' => true]);
        $loser = $this->product('DROP');

        $this->artisan('catalog:absorb DROP KEEP --apply')->assertSuccessful();

        $redirect = Redirect::where('from_slug', $loser->slug)->sole();
        $this->assertSame($winner->id, $redirect->product_id);
        $this->assertSame(301, $redirect->status);

        $this->get(route('shop.product', $loser->slug))
            ->assertRedirect(route('shop.product', $winner->slug));
    }

    public function test_images_stay_behind_unless_asked_for_and_never_steal_the_primary(): void
    {
        $winner = $this->product('KEEP');
        ProductImage::create(['product_id' => $winner->id, 'path' => 'products/k.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $loser = $this->product('DROP');
        ProductImage::create(['product_id' => $loser->id, 'path' => 'products/d1.jpg', 'is_primary' => true, 'sort_order' => 0]);

        $this->artisan('catalog:absorb DROP KEEP --apply')->assertSuccessful();
        $this->assertSame(1, $winner->images()->count(), 'images must not move without the flag');

        $loser2 = $this->product('DROP2');
        ProductImage::create(['product_id' => $loser2->id, 'path' => 'products/d2.jpg', 'is_primary' => true, 'sort_order' => 0]);

        $this->artisan('catalog:absorb DROP2 KEEP --with-images --apply')->assertSuccessful();

        $this->assertSame(2, $winner->images()->count());
        // The keeper's own photo is still the one shown on a card.
        $this->assertSame('products/k.jpg', $winner->fresh()->load('images')->primaryImage()->path);
    }

    public function test_a_dry_run_writes_nothing_and_reports_what_stays_behind(): void
    {
        $winner = $this->product('KEEP');
        $loser = $this->product('DROP');

        $this->artisan('catalog:absorb DROP KEEP')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertNotNull(Product::find($loser->id));
        $this->assertDatabaseCount('redirects', 0);
    }

    public function test_it_refuses_an_unknown_sku_and_absorbing_itself(): void
    {
        $this->product('KEEP');

        $this->artisan('catalog:absorb NOPE KEEP --apply')->assertFailed();
        $this->artisan('catalog:absorb KEEP KEEP --apply')->assertFailed();
    }
}
