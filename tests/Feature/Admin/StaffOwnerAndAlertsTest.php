<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Two Staff-page rules added 2026-09-15:
 *  - only the STORE OWNER (config `retab.owner_email`) changes who is an admin,
 *    and nobody resets the owner's password;
 *  - any admin switches a staff account's alert EMAILS on or off.
 */
class StaffOwnerAndAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'admin', 'email' => config('retab.owner_email')]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_only_an_admin_with_the_owner_address_is_the_owner(): void
    {
        $this->assertTrue($this->owner()->isOwner());
        $this->assertFalse($this->admin()->isOwner());

        // The address alone is not enough: it must also be an admin account.
        config(['retab.owner_email' => 'someone@retab.test']);
        $this->assertFalse(User::factory()->create(['role' => 'editor', 'email' => 'someone@retab.test'])->isOwner());
    }

    public function test_a_non_owner_admin_cannot_demote_another_admin(): void
    {
        $owner = $this->owner();
        $admin = $this->admin();

        $this->actingAs($admin)->put("/admin/users/{$owner->id}/role", ['role' => 'editor'])->assertForbidden();

        $this->assertSame('admin', $owner->fresh()->role);
    }

    public function test_a_non_owner_admin_cannot_promote_an_editor(): void
    {
        $this->owner();
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($this->admin())->put("/admin/users/{$editor->id}/role", ['role' => 'admin'])->assertForbidden();

        $this->assertSame('editor', $editor->fresh()->role);
    }

    public function test_a_non_owner_admin_can_add_an_editor_but_not_an_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/users', ['name' => 'Ed', 'email' => 'ed@retab.test', 'password' => 'password123', 'role' => 'editor'])
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->post('/admin/users', ['name' => 'Ad', 'email' => 'ad@retab.test', 'password' => 'password123', 'role' => 'admin'])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'ad@retab.test']);
    }

    public function test_the_owner_changes_roles(): void
    {
        $owner = $this->owner();
        $admin = $this->admin();

        $this->actingAs($owner)->put("/admin/users/{$admin->id}/role", ['role' => 'editor'])->assertSessionHas('success');
        $this->assertSame('editor', $admin->fresh()->role);

        $this->actingAs($owner)->put("/admin/users/{$admin->id}/role", ['role' => 'admin'])->assertSessionHas('success');
        $this->assertSame('admin', $admin->fresh()->role);
    }

    /**
     * Without this the rule above is decorative: another admin could reset the
     * owner's password, sign in as the owner, and change roles anyway.
     */
    public function test_no_admin_can_reset_the_owners_password(): void
    {
        $owner = User::factory()->create(['role' => 'admin', 'email' => config('retab.owner_email'), 'password' => 'old-password']);

        $this->actingAs($this->admin())
            ->post("/admin/users/{$owner->id}/reset-password", ['password' => 'Brand-new-pass-123'])
            ->assertSessionHas('error', __('messages.admin.password_reset_owner'));

        $this->assertTrue(password_verify('old-password', $owner->fresh()->password));
    }

    public function test_a_non_owner_admin_can_still_reset_another_admins_password(): void
    {
        $other = User::factory()->create(['role' => 'admin', 'password' => 'old-password']);

        $this->actingAs($this->admin())
            ->post("/admin/users/{$other->id}/reset-password", ['password' => 'Brand-new-pass-123'])
            ->assertSessionHas('success');
    }

    public function test_the_page_tells_the_viewer_whether_they_may_change_roles(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get('/admin/users')
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.changeRoles', true)
                ->where('staff.0.is_owner', true)
                ->where('staff.0.email_alerts', true));

        $this->actingAs($this->admin())->get('/admin/users')
            ->assertInertia(fn (Assert $page) => $page->where('can.changeRoles', false));
    }

    public function test_an_admin_switches_a_staff_members_alert_emails(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($this->admin())
            ->put("/admin/users/{$editor->id}/email-alerts", ['enabled' => false])
            ->assertSessionHas('success');

        $this->assertFalse($editor->fresh()->staff_email_alerts);
    }

    public function test_editors_cannot_switch_alert_emails(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $target = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->put("/admin/users/{$target->id}/email-alerts", ['enabled' => false])->assertForbidden();

        $this->assertTrue($target->fresh()->staff_email_alerts);
    }

    public function test_a_switched_off_account_keeps_the_bell_but_gets_no_email(): void
    {
        Notification::fake();

        $on = User::factory()->create(['role' => 'admin']);
        $off = User::factory()->create(['role' => 'editor']);
        $off->forceFill(['staff_email_alerts' => false])->save();

        $order = Order::create([
            'order_number' => 'RTB-ALERTS',
            'customer_name' => 'Zaid',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA'],
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'subtotal' => 100,
            'total' => 125,
        ]);

        Notification::send(User::staff()->get(), new NewOrderNotification($order));

        Notification::assertSentTo($on, NewOrderNotification::class, fn ($n, array $channels) => $channels === ['database', 'mail']);
        Notification::assertSentTo($off, NewOrderNotification::class, fn ($n, array $channels) => $channels === ['database']);
    }

    /** A `.local` staff login can never receive mail, so it must not be sent any. */
    public function test_an_undeliverable_address_gets_the_bell_but_no_email(): void
    {
        $local = User::factory()->create(['role' => 'editor', 'email' => 'editor@retab.local']);

        $order = Order::create([
            'order_number' => 'RTB-LOCAL',
            'customer_name' => 'Zaid',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA'],
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'subtotal' => 100,
            'total' => 125,
        ]);

        $this->assertSame(['database'], (new NewOrderNotification($order))->via($local));
    }
}
