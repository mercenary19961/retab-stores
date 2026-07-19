<?php

namespace Tests\Feature\Admin;

use App\Models\ClientReview;
use App\Models\ContentPage;
use App\Models\Setting;
use App\Models\User;
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
        $this->assertDatabaseMissing('client_reviews', ['author_name' => 'Curated Extra']);
        $this->assertSame(8, ClientReview::count());
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
