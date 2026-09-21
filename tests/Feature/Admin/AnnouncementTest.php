<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The strip above the storefront.
 *
 * 🔑 Most of these are about ONE rule: a NULL date means "no bound on that side".
 * That is what makes the client's "leave it up until I take it down" work without
 * a second flag, and getting it wrong in either direction is invisible — either a
 * notice nobody sees, or one that never goes away.
 */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'a@retab.test', 'password' => bcrypt('x'), 'role' => 'admin',
        ]);
    }

    /** @param  array<string,mixed>  $attributes */
    private function make(array $attributes = []): Announcement
    {
        return Announcement::create([
            'message_ar' => 'التمر المحشي يُشحن داخل الرياض فقط',
            'tone' => 'warning',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    // ---- The scheduling rule ---------------------------------------------------

    public function test_no_dates_at_all_means_it_shows_until_switched_off(): void
    {
        $this->make();

        $this->assertSame(1, Announcement::live()->count());
        $this->assertSame('live', Announcement::firstOrFail()->state());
    }

    public function test_it_does_not_show_before_its_start(): void
    {
        $this->make(['starts_at' => now()->addDay()]);

        $this->assertSame(0, Announcement::live()->count());
        $this->assertSame('scheduled', Announcement::firstOrFail()->state());
    }

    public function test_it_stops_showing_after_its_end(): void
    {
        $this->make(['ends_at' => now()->subHour()]);

        $this->assertSame(0, Announcement::live()->count());
        $this->assertSame('ended', Announcement::firstOrFail()->state());
    }

    /** 🔑 The client's case: started, no end, runs indefinitely. */
    public function test_an_open_ended_window_keeps_showing(): void
    {
        $this->make(['starts_at' => now()->subWeek(), 'ends_at' => null]);

        $this->assertSame(1, Announcement::live()->count());
    }

    public function test_switching_it_off_beats_the_dates(): void
    {
        $this->make(['is_active' => false, 'starts_at' => now()->subDay()]);

        $this->assertSame(0, Announcement::live()->count());
        $this->assertSame('off', Announcement::firstOrFail()->state());
    }

    // ---- Reaching the storefront ------------------------------------------------

    public function test_a_live_announcement_is_shared_with_the_storefront(): void
    {
        $this->make(['message_en' => 'Stuffed dates ship within Riyadh only']);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->has('announcements', 1)
            ->where('announcements.0.tone', 'warning')
            ->where('announcements.0.message_en', 'Stuffed dates ship within Riyadh only'));
    }

    public function test_a_scheduled_announcement_is_not_shared(): void
    {
        $this->make(['starts_at' => now()->addWeek()]);

        $this->get('/')->assertInertia(fn ($page) => $page->has('announcements', 0));
    }

    /**
     * ⚠️ Storefront only. Resolving this on admin pages would be a query per page
     * load for a strip aimed at shoppers that the panel never renders.
     */
    public function test_the_admin_panel_is_not_given_storefront_announcements(): void
    {
        $this->make();

        $this->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertInertia(fn ($page) => $page->where('announcements', []));
    }

    // ---- Managing them -----------------------------------------------------------

    public function test_an_admin_can_create_one(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/announcements', [
                'message_ar' => 'شحن مجاني هذا الأسبوع',
                'tone' => 'success',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('announcements', 1);
    }

    /** 🔴 A window that can never open would be a banner nobody can explain. */
    public function test_an_end_before_the_start_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/announcements', [
                'message_ar' => 'خطأ',
                'tone' => 'info',
                'starts_at' => now()->addWeek()->toDateTimeString(),
                'ends_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('ends_at');

        $this->assertDatabaseCount('announcements', 0);
    }

    /** ⚠️ A scheme-less link resolves to a path on our OWN site, not off it. */
    public function test_a_link_without_a_scheme_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/announcements', [
                'message_ar' => 'اختبار',
                'tone' => 'info',
                'link_url' => 'www.example.com',
            ])
            ->assertSessionHasErrors('link_url');
    }

    public function test_the_toggle_switches_it_off_and_back_on(): void
    {
        $a = $this->make();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/announcements/{$a->id}/toggle");
        $this->assertFalse($a->fresh()->is_active);

        $this->actingAs($admin)->post("/admin/announcements/{$a->id}/toggle");
        $this->assertTrue($a->fresh()->is_active);
    }

    public function test_an_editor_without_the_grant_cannot_manage_them(): void
    {
        $a = $this->make();
        $editor = User::forceCreate([
            'name' => 'Editor', 'email' => 'e@retab.test', 'password' => bcrypt('x'),
            'role' => 'editor', 'permissions' => ['announcements' => ['view' => true, 'manage' => false]],
        ]);

        $this->actingAs($editor)->post("/admin/announcements/{$a->id}/toggle")->assertForbidden();
        $this->actingAs($editor)->delete("/admin/announcements/{$a->id}")->assertForbidden();
        $this->actingAs($editor)->get('/admin/announcements')->assertOk();

        $this->assertTrue($a->fresh()->is_active);
    }
}
