<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The flat shipping fee, edited from the admin top bar.
 *
 * 🔑 The point of these tests is that the shortcut is not a SHORTCUT AROUND
 * anything: same permission, same write, same change-log entry as the full
 * settings form. This is the number that decides what every customer pays.
 */
class ShippingFeeQuickEditTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = CheckoutService::SHIPPING_FEE_KEY;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'a@retab.test', 'password' => bcrypt('x'), 'role' => 'admin',
        ]);
    }

    private function editor(array $permissions): User
    {
        return User::forceCreate([
            'name' => 'Editor', 'email' => 'e@retab.test', 'password' => bcrypt('x'),
            'role' => 'editor', 'permissions' => $permissions,
        ]);
    }

    public function test_an_admin_can_change_the_fee_from_the_top_bar(): void
    {
        Setting::set(self::KEY, 25);

        $this->actingAs($this->admin())
            ->patch('/admin/shipping-fee', [self::KEY => '30'])
            ->assertRedirect();

        $this->assertSame('30', Setting::get(self::KEY));
    }

    /**
     * 🔴 The load-bearing one. A quick control that skipped the audit trail would
     * leave "who changed shipping to 50?" unanswerable.
     */
    public function test_the_change_is_written_to_the_change_log(): void
    {
        Setting::set(self::KEY, 25);

        $this->actingAs($this->admin())->patch('/admin/shipping-fee', [self::KEY => '30']);

        $this->assertDatabaseCount('activity_logs', 1);
        $log = ActivityLog::firstOrFail();
        $this->assertSame(['25'], array_values($log->old_data));
        $this->assertSame(['30'], array_values($log->new_data));
    }

    /** Saving the same number back is not a change, so it must not log one. */
    public function test_saving_an_unchanged_fee_writes_nothing(): void
    {
        Setting::set(self::KEY, 25);

        $this->actingAs($this->admin())->patch('/admin/shipping-fee', [self::KEY => '25']);

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_a_negative_or_missing_fee_is_refused(): void
    {
        Setting::set(self::KEY, 25);
        $admin = $this->admin();

        $this->actingAs($admin)->patch('/admin/shipping-fee', [self::KEY => '-5'])->assertSessionHasErrors(self::KEY);
        $this->actingAs($admin)->patch('/admin/shipping-fee', [])->assertSessionHasErrors(self::KEY);

        $this->assertSame('25', Setting::get(self::KEY));
    }

    /** The shortcut obeys the same grant as the settings page it bypasses. */
    public function test_an_editor_without_settings_edit_is_refused(): void
    {
        Setting::set(self::KEY, 25);

        $this->actingAs($this->editor(['settings' => ['view' => true, 'edit' => false]]))
            ->patch('/admin/shipping-fee', [self::KEY => '30'])
            ->assertForbidden();

        $this->assertSame('25', Setting::get(self::KEY));
    }

    public function test_the_fee_is_shared_to_the_admin_layout(): void
    {
        Setting::set(self::KEY, 25);

        $this->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('shippingFee.amount', 25)
                ->where('shippingFee.canEdit', true)
                ->where('shippingFee.free', false));
    }

    /**
     * ⚠️ Not decoration: a free-shipping window overrides the fee to 0 at
     * checkout, so without this flag the client could set 30, watch customers pay
     * nothing, and conclude the control is broken.
     */
    public function test_an_active_free_shipping_window_is_flagged(): void
    {
        Setting::set(self::KEY, 25);
        Setting::set(CheckoutService::FREE_SHIPPING_ACTIVE_KEY, '1');

        $this->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertInertia(fn ($page) => $page->where('shippingFee.free', true));
    }

    /** No settings.view grant, nothing shared — the badge renders nothing. */
    public function test_an_editor_without_settings_view_is_not_shown_the_fee(): void
    {
        Setting::set(self::KEY, 25);

        $this->actingAs($this->editor(['settings' => ['view' => false, 'edit' => false]]))
            ->get('/admin/dashboard')
            ->assertInertia(fn ($page) => $page->where('shippingFee', null));
    }
}
