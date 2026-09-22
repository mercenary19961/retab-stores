<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StoreEvent;
use App\Support\Media;
use App\Support\MediaTrash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The undo window for deleted uploads.
 *
 * 🔑 What these pin is that NOTHING destroys a file at the moment a client asks
 * for it to go. Before MediaTrash, deleting a hero slide ran Media::delete() on
 * its video and artwork inline, so the bytes were gone from R2 within the second
 * and no amount of change-log machinery could have brought the slide back whole.
 */
class MediaTrashTest extends TestCase
{
    use RefreshDatabase;

    private function heroSlide(array $attributes = []): HeroSlide
    {
        return HeroSlide::create([
            'kind' => 'image',
            'image' => 'hero/art.jpg',
            'is_active' => true,
            'sort_order' => 0,
        ] + $attributes);
    }

    /** Move everything in the queue past the retention window. */
    private function ageTheQueue(): void
    {
        DB::table('media_trash')->update(['trashed_at' => now()->subYear()]);
        HeroSlide::withTrashed()->whereNotNull('deleted_at')->update(['deleted_at' => now()->subYear()]);
        ProductImage::withTrashed()->whereNotNull('deleted_at')->update(['deleted_at' => now()->subYear()]);
    }

    public function test_a_trashed_records_files_survive_the_window_then_go(): void
    {
        Storage::fake(Media::disk());
        $disk = Storage::disk(Media::disk());
        $disk->put('hero/art.jpg', 'x');
        $disk->put('hero/poster.jpg', 'x');

        $slide = $this->heroSlide(['kind' => 'video', 'video' => 'hero/clip.mp4', 'video_poster' => 'hero/poster.jpg']);
        $disk->put('hero/clip.mp4', 'x');
        $slide->delete();

        // Inside the window: nothing is touched, and the row is still restorable.
        MediaTrash::purge();
        $disk->assertExists('hero/art.jpg');
        $disk->assertExists('hero/clip.mp4');
        $this->assertNotNull(HeroSlide::withTrashed()->find($slide->id));

        $this->ageTheQueue();
        $result = MediaTrash::purge();

        $disk->assertMissing('hero/art.jpg');
        $disk->assertMissing('hero/clip.mp4');
        $disk->assertMissing('hero/poster.jpg');
        $this->assertNull(HeroSlide::withTrashed()->find($slide->id), 'the row goes with its files');
        $this->assertSame(1, $result['records']);
        $this->assertSame(3, $result['files']);
    }

    /**
     * 🔴 The safety net, and the reason no call site has to remember to
     * un-schedule anything: a file that something points at again is dropped from
     * the queue rather than destroyed. In practice that is a change-log revert
     * restoring the record while its artwork sat waiting.
     */
    public function test_a_file_referenced_again_is_kept_not_deleted(): void
    {
        Storage::fake(Media::disk());
        $disk = Storage::disk(Media::disk());
        $disk->put('hero/art.jpg', 'x');

        $slide = $this->heroSlide();
        $slide->delete();
        $this->ageTheQueue();

        // The client changes their mind and restores it from the change log.
        $slide->restore();

        $result = MediaTrash::purge();

        $disk->assertExists('hero/art.jpg');
        $this->assertSame(0, $result['files']);
        $this->assertSame(0, $result['records']);
        $this->assertNotNull(HeroSlide::find($slide->id));
    }

    public function test_a_replaced_file_is_queued_and_kept_if_it_comes_back(): void
    {
        Storage::fake(Media::disk());
        $disk = Storage::disk(Media::disk());
        $disk->put('hero/old.jpg', 'x');

        $slide = $this->heroSlide(['image' => 'hero/new.jpg']);
        MediaTrash::schedule('hero/old.jpg', 'hero_slides.image');
        $this->ageTheQueue();

        // Reverting the edit writes the old path back before the purge runs.
        $slide->update(['image' => 'hero/old.jpg']);

        $result = MediaTrash::purge();

        $disk->assertExists('hero/old.jpg');
        $this->assertSame(1, $result['kept']);
        $this->assertDatabaseMissing('media_trash', ['path' => 'hero/old.jpg']);
    }

    public function test_a_dry_run_changes_nothing_but_reports_the_files(): void
    {
        Storage::fake(Media::disk());
        $disk = Storage::disk(Media::disk());
        $disk->put('hero/art.jpg', 'x');

        $slide = $this->heroSlide();
        $slide->delete();
        $this->ageTheQueue();

        $result = MediaTrash::purge(dryRun: true);

        $disk->assertExists('hero/art.jpg');
        $this->assertNotNull(HeroSlide::withTrashed()->find($slide->id));
        // ⚠️ A dry run that reported the record and none of its files would hide
        // the one number an operator checks before shortening the window.
        $this->assertSame(1, $result['records']);
        $this->assertSame(1, $result['files']);
    }

    public function test_scheduling_the_same_path_twice_queues_one_delete(): void
    {
        MediaTrash::schedule('hero/a.jpg', 'first');
        MediaTrash::schedule('hero/a.jpg', 'second');

        $this->assertSame(1, DB::table('media_trash')->where('path', 'hero/a.jpg')->count());
        $this->assertSame('second', DB::table('media_trash')->where('path', 'hero/a.jpg')->value('context'));
    }

    public function test_a_blank_path_is_ignored(): void
    {
        MediaTrash::schedule(null);
        MediaTrash::schedule('');

        $this->assertSame(0, DB::table('media_trash')->count());
    }

    public function test_is_referenced_sees_every_table_in_the_map(): void
    {
        $category = Category::create(['name_ar' => 'ت', 'slug' => 'c-'.uniqid(), 'is_active' => true, 'image' => 'categories/tile.png']);
        $product = Product::create([
            'name_ar' => 'منتج', 'slug' => 'p-'.uniqid(), 'sku' => 'S-'.uniqid(),
            'price' => 10, 'stock' => 1, 'is_active' => false, 'category_id' => $category->id,
        ]);
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/1/a.jpg', 'is_primary' => true]);
        $this->heroSlide(['image' => 'hero/art.jpg', 'video' => 'hero/clip.mp4']);

        $this->assertTrue(MediaTrash::isReferenced('categories/tile.png'));
        $this->assertTrue(MediaTrash::isReferenced('products/1/a.jpg'));
        $this->assertTrue(MediaTrash::isReferenced('hero/art.jpg'));
        $this->assertTrue(MediaTrash::isReferenced('hero/clip.mp4'));
        $this->assertFalse(MediaTrash::isReferenced('hero/nobody-points-here.jpg'));
    }

    /**
     * ⚠️ Trashed rows count as references, deliberately. One inside its window is
     * precisely what a revert is about to restore, and deleting its file would
     * bring the record back rendering nothing.
     */
    public function test_a_trashed_row_still_counts_as_a_reference(): void
    {
        $slide = $this->heroSlide();
        $slide->delete();

        $this->assertTrue(MediaTrash::isReferenced('hero/art.jpg'));
    }

    /**
     * 🔴 The hardest files() branch: an event owns nothing itself, so its artwork
     * sits on rows the foreign key takes with it. `event_hero_banners` and
     * `event_product` both CASCADE on store_events, so a purge that did not
     * collect their paths FIRST would strand every one of them in R2 with nothing
     * left pointing at them — a whole designed campaign's files, unreachable and
     * still billed for.
     */
    public function test_purging_an_event_collects_the_artwork_on_the_rows_that_go_with_it(): void
    {
        Storage::fake(Media::disk());
        $disk = Storage::disk(Media::disk());
        foreach (['events/1/card.webp', 'events/1/hero/banner.webp', 'events/1/hero/phone.webp'] as $path) {
            $disk->put($path, 'x');
        }

        $event = StoreEvent::create([
            'name_ar' => 'اليوم الوطني', 'slug' => 'nd-'.uniqid(),
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
        ]);
        $category = Category::create(['name_ar' => 'ت', 'slug' => 'c-'.uniqid(), 'is_active' => true]);
        $product = Product::create([
            'name_ar' => 'عرض', 'slug' => 'o-'.uniqid(), 'sku' => 'S-'.uniqid(),
            'price' => 96, 'stock' => 5, 'is_active' => false, 'category_id' => $category->id,
        ]);

        $event->products()->attach($product->id, ['banner_image' => 'events/1/card.webp', 'sort_order' => 1]);
        $event->heroBanners()->create([
            'image' => 'events/1/hero/banner.webp',
            'image_mobile' => 'events/1/hero/phone.webp',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $event->delete();
        StoreEvent::withTrashed()->whereKey($event->id)->update(['deleted_at' => now()->subYear()]);

        MediaTrash::purge();

        $disk->assertMissing('events/1/card.webp');
        $disk->assertMissing('events/1/hero/banner.webp');
        $disk->assertMissing('events/1/hero/phone.webp');
        $this->assertNull(StoreEvent::withTrashed()->find($event->id));
        $this->assertSame(0, DB::table('event_product')->where('store_event_id', $event->id)->count());
        // The product itself is untouched — it is catalogue, not campaign artwork.
        $this->assertNotNull(Product::find($product->id));
    }

    /**
     * 🔴 THE GUARD THAT MATTERS MOST. A media column missing from the REFERENCES
     * map is a file that can be deleted while a live record still points at it —
     * surfacing weeks later as an image that silently 404s, long after anyone
     * touched it. This walks the real schema for columns that look like they hold
     * an upload and fails on any the map has not heard of.
     */
    public function test_the_reference_map_covers_every_media_column_in_the_schema(): void
    {
        /*
         * Columns that match the pattern but hold no file of ours:
         *  - media_trash.path IS the queue.
         *  - users/social_accounts.avatar hold a REMOTE Google URL, not a stored
         *    key. Deleting one is meaningless; the file is not on our disk.
         */
        $exempt = [
            'media_trash.path',
            'users.avatar',
            'social_accounts.avatar',
        ];

        $covered = [];
        foreach (self::referenceMap() as $table => $columns) {
            foreach ($columns as $column) {
                $covered[] = "{$table}.{$column}";
            }
        }
        $known = array_merge($covered, $exempt);

        $missing = [];
        foreach (Schema::getTableListing(schema: null, schemaQualified: false) as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                $looksLikeMedia = $column === 'path'
                    || preg_match('/image|photo|video|banner|poster|avatar|logo/i', $column) === 1;

                if ($looksLikeMedia && ! in_array("{$table}.{$column}", $known, true)) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }

        $this->assertSame([], $missing, implode("\n", [
            'These columns look like they hold an uploaded file but are not in',
            'MediaTrash::REFERENCES / JSON_REFERENCES. Add them, or add them to the',
            "exempt list in this test with the reason:\n  ".implode("\n  ", $missing),
        ]));
    }

    /** REFERENCES + JSON_REFERENCES, flattened. @return array<string, list<string>> */
    private static function referenceMap(): array
    {
        $reflection = new \ReflectionClass(MediaTrash::class);
        $map = $reflection->getConstant('REFERENCES');

        foreach ($reflection->getConstant('JSON_REFERENCES') as $table => $column) {
            $map[$table] = [...($map[$table] ?? []), $column];
        }

        return $map;
    }
}
