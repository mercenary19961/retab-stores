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
