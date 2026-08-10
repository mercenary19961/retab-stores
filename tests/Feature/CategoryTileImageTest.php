<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the category tile-image backfill and the homepage behaviour that made a
 * missing value look like a broken asset.
 *
 * Production was rendering four category tiles instead of five because
 * `الشوابير` (slug `rusks`) had `image = NULL`. `featuredCategories` filters on
 * `whereNotNull('image')`, so such a row is dropped from the query entirely rather
 * than falling back to the placeholder the component renders for a null image —
 * which is why it read as a missing picture rather than missing data.
 */
class CategoryTileImageTest extends TestCase
{
    use RefreshDatabase;

    /** The data migration under test, invoked directly. */
    private function runBackfill(): void
    {
        require_once database_path('migrations/2026_08_10_100000_backfill_category_tile_images.php');

        $migration = include database_path('migrations/2026_08_10_100000_backfill_category_tile_images.php');
        $migration->up();
    }

    private function category(string $slug, ?string $image): Category
    {
        return Category::create([
            'name_ar' => 'تصنيف '.$slug,
            'name_en' => $slug,
            'slug' => $slug,
            'image' => $image,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_it_fills_a_missing_tile_image(): void
    {
        $rusks = $this->category('rusks', null);

        $this->runBackfill();

        $this->assertSame('/images/categories/rusks.webp', $rusks->fresh()->image);
    }

    public function test_it_never_overwrites_an_existing_image(): void
    {
        // Categories have no admin UI today, but if one is added this must not
        // clobber a deliberate choice — and it is what makes the migration safe to
        // re-run on every deploy.
        $rusks = $this->category('rusks', '/images/categories/client-choice.webp');

        $this->runBackfill();

        $this->assertSame('/images/categories/client-choice.webp', $rusks->fresh()->image);
    }

    public function test_it_leaves_assorted_without_a_tile(): void
    {
        // Mapped to null by the importer on purpose: it should stay off the homepage.
        $assorted = $this->category('assorted', null);

        $this->runBackfill();

        $this->assertNull($assorted->fresh()->image);
    }

    public function test_a_category_without_an_image_is_dropped_from_the_homepage_not_placeheld(): void
    {
        $this->category('rusks', null);
        $this->category('boxes', '/images/categories/boxes.webp');

        $this->get('/')->assertOk()->assertInertia(
            fn ($page) => $page->where('featuredCategories', fn ($cats) => collect($cats)->pluck('slug')->all() === ['boxes'])
        );
    }

    public function test_the_backfilled_category_then_appears_on_the_homepage(): void
    {
        $this->category('rusks', null);

        $this->runBackfill();

        $this->get('/')->assertOk()->assertInertia(
            fn ($page) => $page->where('featuredCategories', fn ($cats) => collect($cats)->pluck('slug')->all() === ['rusks'])
        );
    }

    public function test_every_mapped_tile_image_exists_in_the_public_directory(): void
    {
        // A backfilled path that does not resolve would swap one broken tile for
        // another, so the files are asserted rather than assumed.
        foreach (['sukkari', 'stuffed-dates', 'rusks', 'boxes', 'occasion-gifts'] as $name) {
            $this->assertFileExists(public_path("images/categories/{$name}.webp"));
        }
    }
}
