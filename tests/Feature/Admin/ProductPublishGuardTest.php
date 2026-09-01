<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ChangeLog\ChangeLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The publish guard: an incomplete product can never be live.
 *
 * A product must have a price, an Arabic name, an English name and at least one
 * image before the storefront may show it. The guard lives on the model rather
 * than in the controller because `is_active` is written from several places —
 * the form, the list toggle, a change-log revert, the importers — and a
 * controller-side check would cover one and silently miss the rest. These tests
 * exercise those paths directly for that reason.
 */
class ProductPublishGuardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function product(array $overrides = []): Product
    {
        $category = Category::create(['name_ar' => 'تمور', 'name_en' => 'Dates', 'slug' => 'dates-'.uniqid()]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name_ar' => 'سكري فاخر',
            'name_en' => 'Premium Sukkari',
            'slug' => 'p-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()),
            'price' => 50,
            'stock' => 10,
            'is_active' => false,
        ], $overrides));
    }

    private function withImage(Product $p): Product
    {
        $p->images()->create(['path' => 'products/x.jpg', 'sort_order' => 1, 'is_primary' => true]);
        $p->unsetRelation('images');

        return $p;
    }

    // ------------------------------------------------------------ the guard

    /** @return array<string, array{array<string,mixed>, string}> */
    public static function incompleteCases(): array
    {
        return [
            'no price' => [['price' => 0], 'price'],
            'no Arabic name' => [['name_ar' => ' '], 'name_ar'],
        ];
    }

    #[DataProvider('incompleteCases')]
    public function test_an_incomplete_product_cannot_be_saved_as_active(array $overrides, string $expected): void
    {
        $product = $this->withImage($this->product($overrides));

        $product->is_active = true;
        $product->save();

        $this->assertFalse($product->fresh()->is_active, "a product with no {$expected} must not go live");
        $this->assertContains($expected, $product->missingForPublish());
    }

    public function test_a_product_with_no_image_cannot_be_active(): void
    {
        $product = $this->product();          // deliberately no image

        $product->is_active = true;
        $product->save();

        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame(['image'], $product->missingForPublish());
    }

    public function test_a_complete_product_goes_live_normally(): void
    {
        $product = $this->withImage($this->product());

        $product->is_active = true;
        $product->save();

        $this->assertTrue($product->fresh()->is_active);
        $this->assertSame([], $product->missingForPublish());
    }

    /**
     * 🔑 An English name is surfaced but never blocking (decided 2026-09-01):
     * the storefront falls back to the Arabic name, so the product reads fine.
     */
    public function test_a_missing_english_name_does_not_block_publishing(): void
    {
        $product = $this->withImage($this->product(['name_en' => null]));

        $product->is_active = true;
        $product->save();

        $this->assertTrue($product->fresh()->is_active, 'an English name must not gate a sale');
        $this->assertSame([], $product->missingForPublish());
        $this->assertSame(['name_en'], $product->publishAdvisories(), 'but it is still flagged');
    }

    public function test_a_missing_english_name_is_not_counted_as_incomplete(): void
    {
        $this->withImage($this->product(['name_en' => null]));

        $this->assertSame(0, Product::incompleteForPublish()->count());
    }

    // ------------------------------------------------------- the write paths

    /**
     * 🔴 The path a controller-only check would miss. A revert writes a whole
     * previous row back, so reverting an edit made while the product still had
     * an image would otherwise republish it with none.
     */
    public function test_a_change_log_revert_cannot_republish_an_incomplete_product(): void
    {
        $product = $this->withImage($this->product());
        $product->is_active = true;
        $product->save();
        $this->assertTrue($product->fresh()->is_active);

        $before = $product->attributesToArray();
        $product->update(['is_active' => false]);
        app(ChangeLogService::class)->logUpdated($product, $before, $product->name_ar);

        // The image goes away, then the "make it live again" state is written back.
        $product->images()->delete();
        $product->unsetRelation('images');
        $product->forceFill(['is_active' => true])->save();

        $this->assertFalse($product->fresh()->is_active, 'a write-back must not republish an image-less product');
    }

    public function test_the_list_toggle_refuses_and_names_what_is_missing(): void
    {
        $product = $this->product(['price' => 0]);   // no price AND no image

        $this->actingAs($this->admin())
            ->patch("/admin/products/{$product->id}/toggle-active")
            ->assertSessionHas('error');

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_the_list_toggle_still_works_for_a_complete_product(): void
    {
        $product = $this->withImage($this->product());

        $this->actingAs($this->admin())
            ->patch("/admin/products/{$product->id}/toggle-active")
            ->assertSessionHas('success');

        $this->assertTrue($product->fresh()->is_active);
    }

    /**
     * 🔴 Regression. update() used to THROW when a product had no images, so the
     * incomplete products — exactly the ones needing attention — could not be
     * edited at all. Saving must succeed; the product just stays hidden.
     */
    public function test_an_image_less_product_can_still_be_edited(): void
    {
        $product = $this->product(['price' => 0]);

        $this->actingAs($this->admin())
            ->put("/admin/products/{$product->id}", [
                'category_id' => $product->category_id,
                'name_ar' => 'اسم جديد',
                'name_en' => 'New name',
                'price' => 25,
                'sku' => $product->sku,
                'stock' => 5,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('اسم جديد', $product->name_ar, 'the edit must be saved');
        $this->assertSame(25.0, (float) $product->price);
        $this->assertFalse($product->is_active, 'but it stays hidden — it still has no image');
    }

    /** Deleting the last image pulls a live product off the storefront. */
    public function test_removing_the_last_image_hides_a_live_product(): void
    {
        $product = $this->withImage($this->product());
        $product->is_active = true;
        $product->save();
        $this->assertTrue($product->fresh()->is_active);

        $image = $product->images()->first();
        $this->actingAs($this->admin())
            ->delete("/admin/products/{$product->id}/images/{$image->id}")
            ->assertSessionHas('error');

        $this->assertFalse($product->fresh()->is_active);
    }

    // ------------------------------------------------- the SQL twin agrees

    /**
     * 🔴 scopeIncompleteForPublish() is SQL and missingForPublish() is PHP. A
     * product one flags and the other does not would be missing from the "needs
     * completing" list while refusing to publish — which reads as a broken
     * toggle. Assert they describe exactly the same set.
     */
    public function test_the_scope_matches_the_php_check_exactly(): void
    {
        $this->withImage($this->product());                        // complete
        $this->withImage($this->product(['price' => 0]));          // no price
        $this->withImage($this->product(['name_ar' => '   ']));    // whitespace AR name
        $this->product();                                          // no image
        $this->product(['price' => 0]);                            // no price and no image

        $viaScope = Product::incompleteForPublish()->pluck('id')->sort()->values()->all();

        $viaPhp = Product::with('images')->get()
            ->filter(fn (Product $p) => $p->missingForPublish() !== [])
            ->pluck('id')->sort()->values()->all();

        $this->assertSame($viaPhp, $viaScope);
        $this->assertCount(4, $viaScope, 'four of the five fixtures cannot be published');
    }

    public function test_the_dashboard_offers_the_incomplete_products_tile(): void
    {
        $this->withImage($this->product(['price' => 0]));

        $tasks = $this->actingAs($this->admin())->get('/admin/dashboard')
            ->assertOk()
            ->viewData('page')['props']['tasks'];

        $tile = collect($tasks)->firstWhere('key', 'productsIncomplete');

        $this->assertNotNull($tile, 'the dashboard must surface products that cannot go live');
        $this->assertSame(1, $tile['count']);
        $this->assertSame('/admin/products?status=incomplete', $tile['href']);
    }
}
