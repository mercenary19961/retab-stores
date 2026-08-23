<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\MailAddress;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Staff accounts can be created on a non-routable address (e.g. editor@retab.local)
 * so they have no reset-by-email: forgetting the password means asking a colleague.
 * That makes this page the ONLY way back into such an account, which is why the
 * reset action exists — and why its guards are the thing worth testing.
 */
class StaffPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function editorWithReset(): User
    {
        $permissions = Permission::DEFAULTS;
        $permissions['staff'] = ['view' => true, 'reset_password' => true];

        return User::factory()->create(['role' => 'editor', 'permissions' => $permissions]);
    }

    // ---------------------------------------------------------------- the address

    public function test_reserved_domains_are_treated_as_undeliverable(): void
    {
        foreach (['a@retab.local', 'a@retab.internal', 'a@x.invalid', 'a@x.test', 'a@localhost', 'a@x.home.arpa'] as $address) {
            $this->assertFalse(MailAddress::isDeliverable($address), "$address should be undeliverable");
        }

        foreach (['a@retab.com.sa', 'a@gmail.com', 'a@example.com'] as $address) {
            $this->assertTrue(MailAddress::isDeliverable($address), "$address should be deliverable");
        }

        // A phone-only account (users.email is nullable) has nothing to mail.
        $this->assertFalse(MailAddress::isDeliverable(null));
    }

    /**
     * The point is not merely that the request is refused — it is that no mail is
     * queued to a domain that cannot exist, because every one of those is a
     * guaranteed bounce against the sending reputation the store's real receipts
     * depend on.
     */
    public function test_forgot_password_refuses_a_non_routable_address(): void
    {
        User::factory()->create(['role' => 'editor', 'email' => 'editor@retab.local']);

        $this->post('/forgot-password', ['email' => 'editor@retab.local'])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    /**
     * The check reads the SUBMITTED address, never a looked-up account, so it
     * cannot be used to discover which accounts exist.
     */
    public function test_the_refusal_is_identical_for_an_address_with_no_account(): void
    {
        $known = $this->post('/forgot-password', ['email' => 'nobody@retab.local']);
        $known->assertSessionHasErrors('email');

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_a_normal_address_still_gets_a_reset_link(): void
    {
        User::factory()->create(['email' => 'customer@example.com']);

        $this->post('/forgot-password', ['email' => 'customer@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    // ---------------------------------------------------------------- the reset

    public function test_an_admin_sets_an_editors_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor', 'email' => 'editor@retab.local', 'password' => 'old-password']);

        $this->actingAs($admin)
            ->post("/admin/users/{$editor->id}/reset-password", ['password' => 'brand-new-pass-99'])
            ->assertSessionHas('success');

        $editor->refresh();
        $this->assertTrue(Hash::check('brand-new-pass-99', $editor->password));
        $this->assertFalse(Hash::check('old-password', $editor->password));
    }

    public function test_an_editor_granted_the_permission_can_reset_another_editor(): void
    {
        $colleague = User::factory()->create(['role' => 'editor', 'password' => 'old-password']);

        $this->actingAs($this->editorWithReset())
            ->post("/admin/users/{$colleague->id}/reset-password", ['password' => 'brand-new-pass-99'])
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('brand-new-pass-99', $colleague->refresh()->password));
    }

    /**
     * 🔴 THE ESCALATION GUARD, and the reason this permission is safe to grant.
     *
     * Without it, `staff.reset_password` would hand out the admin account itself:
     * set the admin's password, sign in as them, and every other guard on this
     * page is moot. Removing the check in the controller turns this test red.
     */
    public function test_a_permitted_editor_cannot_reset_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'old-password']);

        $this->actingAs($this->editorWithReset())
            ->post("/admin/users/{$admin->id}/reset-password", ['password' => 'brand-new-pass-99'])
            ->assertSessionHas('error');

        $this->assertTrue(Hash::check('old-password', $admin->refresh()->password), 'the admin password must be untouched');
    }

    public function test_an_editor_without_the_permission_is_refused(): void
    {
        $colleague = User::factory()->create(['role' => 'editor', 'password' => 'old-password']);

        // A plain editor holds Permission::DEFAULTS, where the whole staff
        // section is denied.
        $this->actingAs(User::factory()->create(['role' => 'editor']))
            ->post("/admin/users/{$colleague->id}/reset-password", ['password' => 'brand-new-pass-99'])
            ->assertForbidden();

        $this->assertTrue(Hash::check('old-password', $colleague->refresh()->password));
    }

    /**
     * Own-password changes go through password.update, which demands the current
     * one. Allowing a self-reset here would let a stolen session lock the real
     * owner out without ever knowing their password.
     */
    public function test_nobody_resets_their_own_password_here_not_even_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'old-password']);

        $this->actingAs($admin)
            ->post("/admin/users/{$admin->id}/reset-password", ['password' => 'brand-new-pass-99'])
            ->assertSessionHas('error');

        $this->assertTrue(Hash::check('old-password', $admin->refresh()->password));
    }

    public function test_a_customer_is_not_a_valid_target(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'password' => 'old-password']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post("/admin/users/{$customer->id}/reset-password", ['password' => 'brand-new-pass-99'])
            ->assertForbidden();

        $this->assertTrue(Hash::check('old-password', $customer->refresh()->password));
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $editor = User::factory()->create(['role' => 'editor', 'password' => 'old-password']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post("/admin/users/{$editor->id}/reset-password", ['password' => 'short'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password', $editor->refresh()->password));
    }

    /**
     * A reset that leaves the previous holder signed in is cosmetic, which is the
     * opposite of what you want when resetting after a suspected compromise.
     */
    public function test_the_targets_live_sessions_are_dropped(): void
    {
        config(['session.driver' => 'database']);

        $editor = User::factory()->create(['role' => 'editor']);
        $other = User::factory()->create(['role' => 'editor']);

        foreach ([$editor, $other] as $u) {
            DB::table('sessions')->insert([
                'id' => 'sess-'.$u->id,
                'user_id' => $u->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => '',
                'last_activity' => time(),
            ]);
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post("/admin/users/{$editor->id}/reset-password", ['password' => 'brand-new-pass-99']);

        $this->assertDatabaseMissing('sessions', ['user_id' => $editor->id]);
        // Only the target's, never everyone's.
        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
    }

    // ---------------------------------------------------------------- the page

    public function test_the_staff_section_is_denied_by_default(): void
    {
        // An editor who predates this section has no `staff` key stored at all,
        // so this also pins that the DEFAULTS fallback DENIES rather than grants.
        $stale = User::factory()->create(['role' => 'editor', 'permissions' => ['orders' => ['view' => true]]]);

        $this->assertFalse($stale->hasPermission('staff.view'));
        $this->assertFalse($stale->hasPermission('staff.reset_password'));
        $this->actingAs($stale)->get('/admin/users')->assertForbidden();
    }

    /**
     * The directory opens up with `staff.view`, but everything that changes WHO
     * has access stays a role rather than a permission — an editor able to edit
     * the grid could simply grant themselves every section.
     */
    public function test_a_permitted_editor_reads_the_directory_but_cannot_change_access(): void
    {
        $editor = $this->editorWithReset();
        $colleague = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/admin/users')->assertOk();

        $this->actingAs($editor)
            ->post('/admin/users', ['name' => 'Mine', 'email' => 'mine@retab.test', 'password' => 'password123', 'role' => 'admin'])
            ->assertForbidden();

        $this->actingAs($editor)
            ->put("/admin/users/{$editor->id}/permissions", ['permissions' => Permission::preset('full')])
            ->assertForbidden();

        $this->actingAs($editor)->put("/admin/users/{$editor->id}/role", ['role' => 'admin'])->assertForbidden();
        $this->actingAs($editor)->delete("/admin/users/{$colleague->id}")->assertForbidden();

        $this->assertTrue($editor->refresh()->isEditor());
    }
}
