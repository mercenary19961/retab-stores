<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckoutService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\ContentPageSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_seeder_populates_the_store_baseline(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->assertSame(25, (int) Setting::get(CheckoutService::SHIPPING_FEE_KEY));
        $this->assertSame('SA9780000145608010008130', Setting::get('bank_iban'));
    }

    public function test_content_page_seeder_publishes_the_legal_pages(): void
    {
        $this->seed(ContentPageSeeder::class);

        $policy = ContentPage::where('slug', 'returns-policy')->first();
        $this->assertNotNull($policy);
        $this->assertTrue((bool) $policy->is_published);
        // Privacy page matters for the launch/consent flow too.
        $this->assertTrue(ContentPage::where('slug', 'privacy-policy')->exists());
    }

    public function test_admin_seeder_creates_a_dev_admin_and_editor_by_default(): void
    {
        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'admin@retab.com.sa')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue(User::where('email', 'editor@retab.com.sa')->where('role', 'editor')->exists());
    }

    public function test_admin_seeder_refuses_a_weak_default_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['retab.admin.password' => null]);

        // Run the seeder object directly — going through `db:seed` in the
        // production env would trigger the console confirmation guard.
        (new AdminUserSeeder)->run();

        $this->assertFalse(User::where('role', 'admin')->exists());
        // The dev editor is never seeded in production either.
        $this->assertFalse(User::where('email', 'editor@retab.com.sa')->exists());
    }

    public function test_admin_seeder_creates_a_hashed_admin_from_env_in_production(): void
    {
        $this->app['env'] = 'production';
        config([
            'retab.admin.email' => 'owner@retab.com.sa',
            'retab.admin.password' => 'S3cret-Handover-Pass',
        ]);

        (new AdminUserSeeder)->run();

        $admin = User::where('email', 'owner@retab.com.sa')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('S3cret-Handover-Pass', $admin->password));
        // No editor auto-created in production.
        $this->assertFalse(User::where('role', 'editor')->exists());
    }
}
