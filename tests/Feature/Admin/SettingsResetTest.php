<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\ClientReview;
use App\Models\ContentPage;
use App\Models\Setting;
use App\Models\User;
use App\Services\ChangeLog\ChangeLogService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SettingsResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reset_restores_settings_content_and_reviews(): void
    {
        // Diverge every affected section from its handover state.
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, 999);
        ContentPage::create(['slug' => 'about', 'title_ar' => 'محرّر', 'title_en' => 'Edited', 'body_ar' => 'x', 'body_en' => 'x', 'is_published' => false]);
        ClientReview::create(['author_name' => 'Curated Extra', 'body' => 'nice', 'rating' => 5, 'source' => 'manual', 'is_active' => true, 'sort_order' => 0]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/settings/reset')
            ->assertRedirect();

        // Settings back to the seeded flat fee.
        $this->assertEquals(25, Setting::get(CheckoutService::SHIPPING_FEE_KEY));

        // Content page overwritten with the handover text + republished.
        $about = ContentPage::where('slug', 'about')->first();
        $this->assertSame('من نحن', $about->title_ar);
        $this->assertTrue($about->is_published);
        // The other two baseline pages are (re)created.
        $this->assertDatabaseHas('content_pages', ['slug' => 'returns-policy']);
        $this->assertDatabaseHas('content_pages', ['slug' => 'contact']);

        // Reviews replaced by exactly the handover pool (curated extra removed).
        $this->assertNull(ClientReview::where('author_name', 'Curated Extra')->first());
        $this->assertSame(8, ClientReview::count());

        // ⚠️ Soft-deleted, not destroyed. This button used to wipe every curated
        // testimonial outright with nothing recorded — the most destructive click
        // in the panel and the least accountable.
        $this->assertNotNull(ClientReview::withTrashed()->where('author_name', 'Curated Extra')->first());
    }

    /**
     * 🔴 Every part of the reset is recorded: the settings as one entry, and each
     * page and each discarded review as their own, so "where did our About page
     * copy go?" is answerable and the pieces can be put back individually.
     */
    public function test_the_reset_is_recorded_in_the_change_log(): void
    {
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, '99');
        $curated = ClientReview::create([
            'author_name' => 'Curated Extra', 'body' => 'nice', 'rating' => 5,
            'source' => 'manual', 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/settings/reset')
            ->assertRedirect();

        // The settings change, as one entry naming the key that moved.
        $settings = ActivityLog::where('subject_type', ActivityLog::SUBJECT_SETTINGS)
            ->latest('id')->firstOrFail();
        $this->assertSame('99', (string) ($settings->old_data[CheckoutService::SHIPPING_FEE_KEY] ?? null));

        // The discarded review, revertable on its own.
        $log = ActivityLog::where('subject_type', ClientReview::class)
            ->where('subject_id', $curated->id)
            ->where('action', ActivityLog::ACTION_DELETED)
            ->firstOrFail();

        $this->assertTrue(app(ChangeLogService::class)->revert($log)->ok);
        $this->assertNotNull(ClientReview::where('author_name', 'Curated Extra')->first());
    }

    public function test_editor_cannot_reset(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'editor']))
            ->post('/admin/settings/reset')
            ->assertForbidden();
    }

    public function test_content_pages_are_edit_only(): void
    {
        // The create/store routes were removed — only index/edit/update remain.
        $this->assertFalse(Route::has('admin.content-pages.create'));
        $this->assertFalse(Route::has('admin.content-pages.store'));
        $this->assertTrue(Route::has('admin.content-pages.edit'));
        $this->assertTrue(Route::has('admin.content-pages.update'));
    }
}
