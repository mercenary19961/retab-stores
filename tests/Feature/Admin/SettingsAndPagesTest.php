<?php

namespace Tests\Feature\Admin;

use App\Models\ContentPage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAndPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('secret'), 'role' => 'admin']);
    }

    public function test_admin_updates_settings(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'shipping_flat_fee' => '35',
            'bank_iban' => 'SA9780000145608010008130',
        ])->assertSessionHas('success');

        $this->assertSame('35', Setting::get('shipping_flat_fee'));
        $this->assertSame('SA9780000145608010008130', Setting::get('bank_iban'));
    }

    public function test_customers_cannot_touch_settings(): void
    {
        $user = User::create(['name' => 'C', 'email' => 'c@test.com', 'password' => bcrypt('secret')]);

        $this->actingAs($user)->put('/admin/settings', ['shipping_flat_fee' => '1'])->assertForbidden();
    }

    public function test_published_page_renders_and_unpublished_404s(): void
    {
        ContentPage::create([
            'slug' => 'returns-policy', 'title_ar' => 'سياسة', 'body_ar' => 'نص', 'is_published' => true,
        ]);
        ContentPage::create([
            'slug' => 'draft', 'title_ar' => 'مسودة', 'body_ar' => 'نص', 'is_published' => false,
        ]);

        $this->get('/pages/returns-policy')->assertOk();
        $this->get('/pages/draft')->assertNotFound();
    }

    public function test_admin_creates_and_updates_a_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/content-pages', [
            'slug' => 'about', 'title_ar' => 'من نحن', 'title_en' => 'About',
            'body_ar' => 'نص', 'body_en' => 'Body', 'is_published' => true,
        ])->assertRedirect('/admin/content-pages');

        $page = ContentPage::where('slug', 'about')->firstOrFail();

        $this->actingAs($admin)->put("/admin/content-pages/{$page->id}", [
            'slug' => 'about', 'title_ar' => 'من نحن نحن', 'title_en' => 'About',
            'body_ar' => 'نص جديد', 'body_en' => 'Body', 'is_published' => false,
        ])->assertRedirect('/admin/content-pages');

        $this->assertFalse($page->fresh()->is_published);
    }
}
