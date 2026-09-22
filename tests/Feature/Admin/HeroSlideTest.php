<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\EventHeroBanner;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StoreEvent;
use App\Models\User;
use App\Services\ChangeLog\ChangeLogService;
use App\Support\HeroBanners;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The homepage hero, managed at /admin/hero.
 *
 * 🔑 The load-bearing claims here are (a) the admin's status pill agrees with what
 * the storefront actually shows, and (b) the mode setting really governs whether a
 * running campaign takes the homepage over. Both are invisible to tsc and to a
 * status-code assertion.
 */
class HeroSlideTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'A', 'email' => 'a@retab.test', 'password' => bcrypt('x'), 'role' => 'admin',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function slide(array $attributes = []): HeroSlide
    {
        return HeroSlide::create([
            'kind' => HeroSlide::KIND_IMAGE,
            'image' => 'hero/a.jpg',
            'alt_ar' => 'شريحة',
            'is_active' => true,
            'sort_order' => 0,
            ...$attributes,
        ]);
    }

    // ---- The window ---------------------------------------------------------

    public function test_a_slide_with_no_dates_runs_until_it_is_switched_off(): void
    {
        $slide = $this->slide();

        $this->assertSame('live', $slide->state());
        $this->assertCount(1, HeroBanners::ownSlides());

        $slide->update(['is_active' => false]);

        $this->assertSame('off', $slide->fresh()->state());
        $this->assertSame([], HeroBanners::ownSlides());
    }

    public function test_the_window_is_honoured_from_both_ends(): void
    {
        $this->slide(['starts_at' => now()->addDay()]);
        $this->slide(['ends_at' => now()->subDay()]);

        $this->assertSame([], HeroBanners::ownSlides());
    }

    /**
     * 🔴 The admin pill and the storefront MUST agree. A row the panel calls
     * "live" that the homepage does not show reads as a broken storefront, and
     * this is the page the client checks their own work on.
     */
    public function test_the_admin_state_and_the_storefront_never_disagree(): void
    {
        $this->slide(); // live
        $this->slide(['is_active' => false]);
        $this->slide(['starts_at' => now()->addDay()]);
        $this->slide(['ends_at' => now()->subDay()]);
        $this->slide(['image' => null]);                               // incomplete
        $this->slide(['kind' => HeroSlide::KIND_VIDEO, 'image' => null]); // incomplete
        $this->slide(['kind' => HeroSlide::KIND_VIDEO, 'image' => null, 'video' => 'hero/v.mp4']);

        $shown = collect(HeroBanners::ownSlides())->pluck('id')->all();
        $claimed = HeroSlide::all()
            ->filter(fn (HeroSlide $s) => $s->state() === 'live')
            ->map(fn (HeroSlide $s) => 'slide-'.$s->id)
            ->values()
            ->all();

        sort($shown);
        sort($claimed);
        $this->assertSame($claimed, $shown, 'scopeLive() and state() disagree about which slides are live');
    }

    /**
     * 🔴 A slide whose file never arrived would paint an empty band across the
     * top of the homepage. Hidden, and flagged as needing attention.
     */
    public function test_a_slide_with_no_file_is_hidden_rather_than_broken(): void
    {
        $image = $this->slide(['image' => null]);
        $video = $this->slide(['kind' => HeroSlide::KIND_VIDEO, 'image' => null, 'video' => null]);

        $this->assertSame('incomplete', $image->state());
        $this->assertSame('incomplete', $video->state());
        $this->assertSame([], HeroBanners::ownSlides());
    }

    // ---- The mode ------------------------------------------------------------

    public function test_the_mode_defaults_to_the_behaviour_that_shipped_before_this_feature(): void
    {
        // A store that never opens the new page must behave exactly as it did.
        $this->assertSame(HeroBanners::MODE_TAKEOVER, HeroBanners::mode());
    }

    public function test_an_unknown_stored_mode_falls_back_rather_than_breaking_the_homepage(): void
    {
        Setting::set(HeroBanners::MODE_KEY, 'nonsense');

        $this->assertSame(HeroBanners::MODE_TAKEOVER, HeroBanners::mode());
    }

    /** With no campaign running, every mode shows the client's own slides. */
    public function test_own_slides_show_in_every_mode_when_no_campaign_is_running(): void
    {
        $this->slide();

        foreach (HeroBanners::MODES as $mode) {
            Setting::set(HeroBanners::MODE_KEY, $mode);
            $this->assertCount(1, HeroBanners::live(), "mode {$mode} lost the client's own slide");
        }
    }

    /**
     * A running campaign with one live banner. Mirrors the fixture in
     * StoreEventOffersAndBannersTest: the banner's event needs a live offer or
     * EventHeroBanner::scopeLive() correctly refuses to show it.
     */
    private function runningCampaign(): EventHeroBanner
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name_ar' => 'عرض', 'slug' => 'p-'.uniqid(),
            'price' => 96, 'sku' => 'T-'.uniqid(), 'stock' => 5, 'is_active' => true,
        ]);
        $product->images()->create(['path' => "products/{$product->id}/a.webp", 'sort_order' => 1, 'is_primary' => true]);

        $event = StoreEvent::create([
            'name_ar' => 'اليوم الوطني', 'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(), 'is_active' => true,
        ]);
        $event->products()->attach($product->id);

        return EventHeroBanner::create([
            'store_event_id' => $event->id, 'image' => 'events/1/hero/a.webp',
            'product_id' => $product->id, 'is_active' => true, 'sort_order' => 0,
        ]);
    }

    /**
     * 🔴 THE HEADLINE BEHAVIOUR. The client asked to curate the hero rather than
     * have a campaign silently take it over, so each mode must genuinely differ
     * while a campaign is running. Without a live campaign in the fixture every
     * one of these would pass vacuously.
     */
    public function test_each_mode_composes_the_hero_differently_while_a_campaign_runs(): void
    {
        $this->runningCampaign();
        $this->slide();

        // Sanity: the fixture really does produce a live campaign banner, so a
        // failure below is about the mode rather than about a broken fixture.
        $this->assertCount(1, HeroBanners::campaignBanners(), 'fixture produced no live campaign banner');

        Setting::set(HeroBanners::MODE_KEY, HeroBanners::MODE_TAKEOVER);
        $takeover = HeroBanners::live();
        $this->assertCount(1, $takeover);
        $this->assertStringStartsWith('event-', $takeover[0]['id']);

        Setting::set(HeroBanners::MODE_KEY, HeroBanners::MODE_MERGE);
        $merge = HeroBanners::live();
        $this->assertCount(2, $merge);
        $this->assertStringStartsWith('event-', $merge[0]['id'], 'campaign banners lead in merge mode');
        $this->assertStringStartsWith('slide-', $merge[1]['id']);

        Setting::set(HeroBanners::MODE_KEY, HeroBanners::MODE_MINE);
        $mine = HeroBanners::live();
        $this->assertCount(1, $mine);
        $this->assertStringStartsWith('slide-', $mine[0]['id']);
    }

    /**
     * ⚠️ Takeover must fall THROUGH to the client's slides when the campaign has
     * no live banner, or ending a campaign would leave the homepage on its
     * built-in pictures while the client's own slides sat switched on.
     */
    public function test_takeover_falls_through_when_the_campaign_has_no_live_banner(): void
    {
        $banner = $this->runningCampaign();
        $banner->update(['is_active' => false]);
        $this->slide();

        Setting::set(HeroBanners::MODE_KEY, HeroBanners::MODE_TAKEOVER);

        $live = HeroBanners::live();
        $this->assertCount(1, $live);
        $this->assertStringStartsWith('slide-', $live[0]['id']);
    }

    /** 🔑 Ids from the two tables must never collide in the carousel's keys. */
    public function test_ids_from_both_sources_are_distinct(): void
    {
        $this->runningCampaign();
        $this->slide();
        Setting::set(HeroBanners::MODE_KEY, HeroBanners::MODE_MERGE);

        $ids = collect(HeroBanners::live())->pluck('id');

        $this->assertSame($ids->count(), $ids->unique()->count(), 'two sources produced a colliding id');
    }

    // ---- The payload the storefront consumes ---------------------------------

    /**
     * 🔑 IDs are prefixed by source because the hero is now composed from two
     * tables. Bare numeric ids would collide the moment a campaign banner and a
     * slide shared one, and React would reuse the wrong element.
     */
    public function test_slide_ids_are_namespaced_for_the_carousel(): void
    {
        $slide = $this->slide();

        $this->assertSame('slide-'.$slide->id, HeroBanners::ownSlides()[0]['id']);
    }

    /**
     * ⚠️ A video's POSTER goes in `image`, and `video` is never variant-mapped:
     * the variant pipeline only makes WebP stills, so asking it for one would
     * hand the player a 404.
     */
    public function test_a_video_slide_ships_its_poster_as_the_still(): void
    {
        $this->slide([
            'kind' => HeroSlide::KIND_VIDEO,
            'image' => null,
            'video' => 'hero/clip.mp4',
            'video_poster' => 'hero/poster.jpg',
        ]);

        $payload = HeroBanners::ownSlides()[0];

        $this->assertSame('video', $payload['kind']);
        $this->assertStringContainsString('clip.mp4', (string) $payload['video']);
        $this->assertStringContainsString('poster', (string) $payload['image']);
    }

    // ---- The admin page ------------------------------------------------------

    /**
     * 🔑 The preview is built by the STOREFRONT's own method. This asserts the
     * page actually ships it, because a preview computed a second way would agree
     * until the rules changed and then quietly lie to the client.
     */
    public function test_the_page_previews_the_real_composed_hero(): void
    {
        $this->slide();

        $this->actingAs($this->admin())
            ->get('/admin/hero')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/hero/index')
                ->has('slides', 1)
                ->has('preview', 1)
                ->where('mode', HeroBanners::MODE_TAKEOVER));
    }

    public function test_an_editor_without_hero_manage_cannot_change_anything(): void
    {
        $editor = User::forceCreate([
            'name' => 'E', 'email' => 'e@retab.test', 'password' => bcrypt('x'), 'role' => 'editor',
            'permissions' => ['hero' => ['view' => true, 'manage' => false]],
        ]);

        $slide = $this->slide();

        $this->actingAs($editor)->get('/admin/hero')->assertOk();
        $this->actingAs($editor)->patch("/admin/hero/{$slide->id}/toggle")->assertForbidden();
        $this->actingAs($editor)->delete("/admin/hero/{$slide->id}")->assertForbidden();
        $this->actingAs($editor)->post('/admin/hero/mode', ['mode' => HeroBanners::MODE_MINE])->assertForbidden();

        $this->assertTrue($slide->fresh()->is_active);
    }

    public function test_an_end_before_the_start_is_refused(): void
    {
        Storage::fake(Media::disk());

        $this->actingAs($this->admin())->post('/admin/hero', [
            'kind' => 'image',
            'image' => UploadedFile::fake()->image('a.jpg', 1920, 960),
            'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('ends_at');

        $this->assertSame(0, HeroSlide::count());
    }

    public function test_creating_an_image_slide_stores_the_upload(): void
    {
        Storage::fake(Media::disk());

        $this->actingAs($this->admin())->post('/admin/hero', [
            'kind' => 'image',
            'image' => UploadedFile::fake()->image('hero.jpg', 1920, 960),
            'alt_ar' => 'تمور',
        ])->assertRedirect();

        $slide = HeroSlide::sole();
        $this->assertNotNull($slide->image);
        Storage::disk(Media::disk())->assertExists($slide->image);
    }

    /**
     * 🔴 THE BUG THE BROWSER CAUGHT AND THE SUITE MISSED.
     *
     * multipart/form-data carries strings only, so the admin form sends every
     * field as one. The earlier create test omitted `is_active` entirely and so
     * exercised the default rather than this path, while in a real browser the
     * whole form 302'd back rejected and nothing was ever saved.
     *
     * The page now serialises the boolean as 1/0 before posting. This pins the
     * SHAPE a browser actually sends, so the two cannot drift apart again.
     */
    public function test_the_create_form_accepts_the_payload_a_browser_really_sends(): void
    {
        Storage::fake(Media::disk());

        $this->actingAs($this->admin())->post('/admin/hero', [
            'kind' => 'image',
            'image' => UploadedFile::fake()->image('a.jpg', 1920, 960),
            'alt_ar' => 'شريحة',
            // Everything below arrives as a string from multipart, including the
            // boolean, which the page converts to 1/0 precisely because "true"
            // is not a value Laravel's `boolean` rule accepts.
            'is_active' => '1',
            'sort_order' => '0',
            'href' => '',
            'alt_en' => '',
            'starts_at' => '',
            'ends_at' => '',
            // 🔴 The keys for the OTHER kind arrive too, empty. Omitting them is
            // exactly how the first version of this test passed while the page
            // was rejected in a browser with "The video field must be a file."
            'video' => '',
            'video_poster' => '',
            'image_mobile' => '',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, HeroSlide::count());
        $this->assertTrue(HeroSlide::sole()->is_active);
    }

    /**
     * 🔑 An edit that only changes the schedule must not force the client to
     * re-upload artwork that is already there.
     */
    public function test_editing_without_a_new_file_keeps_the_existing_art(): void
    {
        Storage::fake(Media::disk());
        $slide = $this->slide(['image' => 'hero/original.jpg']);

        $this->actingAs($this->admin())->post("/admin/hero/{$slide->id}", [
            'kind' => 'image',
            'alt_ar' => 'محدث',
        ])->assertRedirect();

        $this->assertSame('hero/original.jpg', $slide->fresh()->image);
        $this->assertSame('محدث', $slide->fresh()->alt_ar);
    }

    /**
     * 🔴 The inverse of what this test used to assert, and the change is the point.
     *
     * It previously demanded that deleting a slide destroy its artwork on the
     * spot, which is exactly what made the delete unrecoverable: the client's
     * video was gone from R2 before anyone could ask for it back, so no
     * change-log entry could ever have undone it. The slide is soft-deleted now
     * and the files are left alone until media:purge-trash closes the window.
     */
    public function test_deleting_a_slide_keeps_its_files_so_the_delete_can_be_undone(): void
    {
        Storage::fake(Media::disk());
        $disk = Storage::disk(Media::disk());
        $disk->put('hero/kept.jpg', 'x');

        $slide = $this->slide(['image' => 'hero/kept.jpg']);

        $this->actingAs($this->admin())->delete("/admin/hero/{$slide->id}")->assertRedirect();

        // Gone from the panel and the storefront...
        $this->assertSame(0, HeroSlide::count());
        // ...but restorable, with its artwork still there to restore.
        $this->assertSame(1, HeroSlide::withTrashed()->count());
        $disk->assertExists('hero/kept.jpg');
    }

    public function test_deleting_a_slide_is_recorded_and_can_be_undone(): void
    {
        Storage::fake(Media::disk());
        $slide = $this->slide(['image' => 'hero/kept.jpg', 'alt_ar' => 'شريحة']);

        $this->actingAs($this->admin())->delete("/admin/hero/{$slide->id}")->assertRedirect();

        $log = ActivityLog::where('subject_type', HeroSlide::class)
            ->where('subject_id', $slide->id)
            ->where('action', ActivityLog::ACTION_DELETED)
            ->firstOrFail();

        $this->assertSame('شريحة', $log->label);

        $result = app(ChangeLogService::class)->revert($log);

        $this->assertTrue($result->ok);
        $this->assertSame(1, HeroSlide::count(), 'the slide should be back on the page');
        $this->assertSame('hero/kept.jpg', HeroSlide::first()->image);
    }

    /**
     * ⚠️ The replaced file is queued, never deleted inline. Deleting it made the
     * change-log entry for the edit a lie: reverting writes the old path back,
     * and the file it names would already be gone.
     */
    public function test_replacing_the_artwork_keeps_the_old_file_until_the_window_closes(): void
    {
        Storage::fake(Media::disk());
        $disk = Storage::disk(Media::disk());
        $disk->put('hero/old.jpg', 'x');

        $slide = $this->slide(['image' => 'hero/old.jpg']);

        $this->actingAs($this->admin())->post("/admin/hero/{$slide->id}", [
            'kind' => 'image',
            'image' => UploadedFile::fake()->image('new.jpg', 1440, 720),
        ])->assertRedirect();

        $this->assertNotSame('hero/old.jpg', $slide->fresh()->image);
        $disk->assertExists('hero/old.jpg');
        $this->assertDatabaseHas('media_trash', ['path' => 'hero/old.jpg']);
    }

    public function test_reordering_swaps_with_the_neighbour(): void
    {
        $first = $this->slide(['sort_order' => 0, 'alt_ar' => 'أ']);
        $second = $this->slide(['sort_order' => 1, 'alt_ar' => 'ب']);

        $this->actingAs($this->admin())->post("/admin/hero/{$second->id}/reorder", ['direction' => 'up'])->assertRedirect();

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(0, $second->fresh()->sort_order);
    }

    /**
     * 🔴 Every new slide is created at sort_order 0, so TIES are the ordinary
     * state of a fresh list, not an edge case. The first implementation swapped
     * two identical values and moved nothing, which in a browser looked like the
     * reorder buttons were simply dead.
     */
    public function test_reordering_works_when_every_slide_shares_the_default_order(): void
    {
        $first = $this->slide(['sort_order' => 0, 'alt_ar' => 'أ']);
        $second = $this->slide(['sort_order' => 0, 'alt_ar' => 'ب']);

        $this->actingAs($this->admin())->post("/admin/hero/{$second->id}/reorder", ['direction' => 'up'])->assertRedirect();

        $order = collect(HeroBanners::ownSlides())->pluck('id')->all();
        $this->assertSame(['slide-'.$second->id, 'slide-'.$first->id], $order, 'the moved slide did not come first');
    }

    // ---- Focal point + drag-and-drop ordering --------------------------------

    /**
     * 🔴 The hero is a fixed 2:1 band with `object-cover`, so art that is not 2:1
     * is CROPPED — centred, which is the wrong guess for most photographs. The
     * client uploaded an image, saw it cut, and had no way to say which part
     * mattered. This is that control reaching the storefront.
     */
    public function test_the_focal_point_reaches_the_storefront(): void
    {
        $this->slide(['focal_x' => 25, 'focal_y' => 80]);

        $this->assertSame('25% 80%', HeroBanners::ownSlides()[0]['focal']);
    }

    /** Slides saved before the focal point existed keep the centred crop they had. */
    public function test_a_slide_without_a_focal_point_stays_centred(): void
    {
        $this->assertSame('50% 50%', $this->slide()->focalPosition());
        $this->assertSame('50% 50%', HeroBanners::ownSlides()[0]['focal']);
    }

    public function test_a_focal_point_outside_the_picture_is_refused(): void
    {
        Storage::fake(Media::disk());

        $this->actingAs($this->admin())->post('/admin/hero', [
            'kind' => 'image',
            'image' => UploadedFile::fake()->image('a.jpg', 1920, 960),
            'focal_x' => 140,
        ])->assertSessionHasErrors('focal_x');
    }

    /** Drag-and-drop submits a whole new order at once. */
    public function test_dragging_applies_the_whole_order(): void
    {
        $a = $this->slide(['alt_ar' => 'أ']);
        $b = $this->slide(['alt_ar' => 'ب']);
        $c = $this->slide(['alt_ar' => 'ج']);

        $this->actingAs($this->admin())
            ->post('/admin/hero/reorder', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertRedirect();

        $this->assertSame(
            ['slide-'.$c->id, 'slide-'.$a->id, 'slide-'.$b->id],
            collect(HeroBanners::ownSlides())->pluck('id')->all(),
        );
    }

    /**
     * ⚠️ The preview also contains CAMPAIGN banners, which live in another table.
     * Their ids arriving in the payload must be ignored, not blow up the request.
     */
    public function test_reordering_ignores_ids_that_are_not_hero_slides(): void
    {
        $a = $this->slide(['alt_ar' => 'أ']);

        $this->actingAs($this->admin())
            ->post('/admin/hero/reorder', ['ids' => [999999, $a->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $a->fresh()->sort_order);
    }

    public function test_an_editor_without_hero_manage_cannot_drag(): void
    {
        $editor = User::forceCreate([
            'name' => 'E2', 'email' => 'e2@retab.test', 'password' => bcrypt('x'), 'role' => 'editor',
            'permissions' => ['hero' => ['view' => true, 'manage' => false]],
        ]);
        $a = $this->slide();

        $this->actingAs($editor)->post('/admin/hero/reorder', ['ids' => [$a->id]])->assertForbidden();
    }

    public function test_the_mode_is_stored_through_the_setting(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/hero/mode', ['mode' => HeroBanners::MODE_MINE])
            ->assertRedirect();

        $this->assertSame(HeroBanners::MODE_MINE, HeroBanners::mode());
    }

    public function test_an_unknown_mode_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/hero/mode', ['mode' => 'whatever'])
            ->assertSessionHasErrors('mode');
    }
}
