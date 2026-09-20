<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Models\ContentPage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsAndPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('secret'), 'role' => 'admin']);
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

    public function test_admin_can_switch_a_payment_method_off(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'shipping_flat_fee' => '35',
            'payment_card_enabled' => false,
            'payment_tamara_enabled' => false,
            'payment_bank_transfer_enabled' => true,
        ])->assertSessionHas('success');

        $this->assertSame(['bank_transfer'], PaymentMethod::enabledValues());
    }

    /**
     * 🔴 Refused at the save, not discovered later by a shopper who cannot pay.
     */
    public function test_the_last_payment_method_cannot_be_switched_off(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'shipping_flat_fee' => '35',
            'payment_card_enabled' => false,
            'payment_tamara_enabled' => false,
            'payment_bank_transfer_enabled' => false,
        ])->assertSessionHas('error');

        // Nothing was written: all three are still on.
        $this->assertCount(3, PaymentMethod::enabled());
    }

    /**
     * The guard judges the RESULTING state, so a form that posts only some of the
     * toggles is measured against what is already stored rather than against this
     * request alone.
     */
    public function test_the_guard_accounts_for_methods_the_form_did_not_post(): void
    {
        Setting::set(PaymentMethod::Card->settingKey(), '0');
        Setting::set(PaymentMethod::Tamara->settingKey(), '0');

        // Only bank transfer is left, and this request would switch it off.
        $this->actingAs($this->admin())->put('/admin/settings', [
            'shipping_flat_fee' => '35',
            'payment_bank_transfer_enabled' => false,
        ])->assertSessionHas('error');

        $this->assertSame(['bank_transfer'], PaymentMethod::enabledValues());
    }

    public function test_footer_prop_falls_back_to_defaults_then_reflects_override(): void
    {
        // Unset → the shared footer prop serves the default.
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('footer.contact_email', 'Info@retab.com.sa'));

        // Admin edits it → the storefront reflects the new value; blank socials
        // still fall back to their default (filled() guard).
        $this->actingAs($this->admin())->put('/admin/settings', [
            'shipping_flat_fee' => '35',
            'contact_email' => 'hello@retab.com.sa',
        ])->assertSessionHas('success');

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('footer.contact_email', 'hello@retab.com.sa')
            ->where('footer.social_facebook', 'https://www.facebook.com/retab_dates'));
    }

    public function test_settings_reject_invalid_footer_values(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'shipping_flat_fee' => '35',
            'contact_email' => 'not-an-email',
            'social_facebook' => 'not-a-url',
        ])->assertSessionHasErrors(['contact_email', 'social_facebook']);
    }

    public function test_customers_cannot_touch_settings(): void
    {
        $user = User::forceCreate(['name' => 'C', 'email' => 'c@test.com', 'password' => bcrypt('secret')]);

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

    public function test_admin_updates_a_page(): void
    {
        // Pages are edit-only (the three baseline pages ship seeded; no create route).
        $page = ContentPage::create([
            'slug' => 'about', 'title_ar' => 'من نحن', 'title_en' => 'About',
            'body_ar' => 'نص', 'body_en' => 'Body', 'is_published' => true,
        ]);

        $this->actingAs($this->admin())->put("/admin/content-pages/{$page->id}", [
            'slug' => 'about', 'title_ar' => 'من نحن نحن', 'title_en' => 'About',
            'body_ar' => 'نص جديد', 'body_en' => 'Body', 'is_published' => false,
        ])->assertRedirect('/admin/content-pages');

        $this->assertFalse($page->fresh()->is_published);
        $this->assertSame('من نحن نحن', $page->fresh()->title_ar);
    }
}
