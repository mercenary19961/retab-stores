<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_ships_consent_mode_defaults_denied(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee("gtag('consent', 'default'", false)
            ->assertSee("analytics_storage: 'denied'", false);
    }

    public function test_admin_pages_do_not_ship_the_consent_block(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('secret'), 'role' => 'admin',
        ]);

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee("gtag('consent', 'default'", false);
    }

    public function test_gtm_loads_only_when_a_container_id_is_configured(): void
    {
        // Unset by default → the tag manager never loads (Consent Mode still ships).
        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com/gtm.js', false);

        // Configured → the loader appears with the container id.
        config(['services.gtm.container_id' => 'GTM-TEST123']);

        $this->get('/')->assertOk()
            ->assertSee('googletagmanager.com/gtm.js', false)
            ->assertSee('GTM-TEST123', false);
    }

    public function test_privacy_policy_page_renders(): void
    {
        ContentPage::create([
            'slug' => 'privacy-policy', 'title_ar' => 'سياسة الخصوصية', 'body_ar' => 'نص', 'is_published' => true,
        ]);

        $this->get('/pages/privacy-policy')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('shop/page')->where('page.slug', 'privacy-policy'),
        );
    }
}
