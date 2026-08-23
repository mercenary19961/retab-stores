<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_default_permissions_gate_sections(): void
    {
        $editor = User::factory()->create(['role' => 'editor']); // null → Permission::DEFAULTS

        // Granted by default.
        $this->actingAs($editor)->get('/admin/orders')->assertOk();
        $this->actingAs($editor)->get('/admin/change-log')->assertOk();

        // Denied by default: settings.view=false, orders.export=false, change_log.revert=false.
        $this->actingAs($editor)->get('/admin/settings')->assertForbidden();
        $this->actingAs($editor)->get('/admin/orders/export?format=csv')->assertForbidden();
    }

    /**
     * The staff page used to be flatly admin-only. It is now readable by anyone
     * holding `staff.view`, so that a trusted editor can reset a colleague's
     * password — but that permission is denied by default, so an editor still
     * cannot reach it unless an admin has said so. Every WRITE on the page is
     * still admin-only (see StaffPasswordResetTest).
     */
    public function test_the_staff_page_needs_admin_or_an_explicit_grant(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/users')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'editor']))->get('/admin/users')->assertForbidden();

        $permissions = Permission::DEFAULTS;
        $permissions['staff']['view'] = true;
        $granted = User::factory()->create(['role' => 'editor', 'permissions' => $permissions]);

        $this->actingAs($granted)->get('/admin/users')->assertOk();
    }

    public function test_admin_creates_an_editor_with_default_permissions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/users', ['name' => 'New Editor', 'email' => 'ed@retab.test', 'password' => 'password123', 'role' => 'editor'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'ed@retab.test', 'role' => 'editor']);
    }

    /**
     * Admins were only ever creatable by the seeder over a production shell,
     * which meant the client could not add a second one and a locked-out admin
     * had no in-app way back.
     */
    public function test_admin_creates_another_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/users', ['name' => 'Second Admin', 'email' => 'admin2@retab.test', 'password' => 'password123', 'role' => 'admin'])
            ->assertSessionHas('success');

        $created = User::where('email', 'admin2@retab.test')->firstOrFail();

        $this->assertTrue($created->isAdmin());
        // Admins bypass every check, so a stored map would be inert but read as
        // meaningful the next time somebody looks at the row.
        $this->assertNull($created->permissions);
        $this->actingAs($created)->get('/admin/settings')->assertOk();
    }

    public function test_the_role_must_be_admin_or_editor(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/users', ['name' => 'Nope', 'email' => 'nope@retab.test', 'password' => 'password123', 'role' => 'owner'])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'nope@retab.test']);
    }

    public function test_an_editor_can_be_promoted_to_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)->put("/admin/users/{$editor->id}/role", ['role' => 'admin'])->assertSessionHas('success');

        $promoted = $editor->fresh();
        $this->assertTrue($promoted->isAdmin());
        // Settings is denied by DEFAULTS, so reaching it proves the role took effect.
        $this->actingAs($promoted)->get('/admin/settings')->assertOk();
        $this->actingAs($promoted)->get('/admin/users')->assertOk();
    }

    public function test_an_admin_can_be_demoted_while_another_admin_remains(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put("/admin/users/{$other->id}/role", ['role' => 'editor'])->assertSessionHas('success');

        $this->assertTrue($other->fresh()->isEditor());
        $this->actingAs($other->fresh())->get('/admin/users')->assertForbidden();
    }

    /**
     * 🔴 The invariant the whole page rests on: there is always at least one
     * admin. Zero admins means nobody can reach this page and the only way back
     * is a shell on the production container.
     */
    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // The MESSAGE is asserted, not merely that it failed: with the guards in
        // the other order this refusal reads "not your own role", which does not
        // tell the sole admin what to do about it.
        $this->actingAs($admin)->put("/admin/users/{$admin->id}/role", ['role' => 'editor'])
            ->assertSessionHas('error', __('messages.admin.role_last_admin'));

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    /**
     * With a second admin present the count guard passes, so this is the branch
     * that stops an admin demoting themselves out of the page mid-flow.
     */
    public function test_you_cannot_change_your_own_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'admin']); // count guard satisfied

        $this->actingAs($admin)->put("/admin/users/{$admin->id}/role", ['role' => 'editor'])
            ->assertSessionHas('error', __('messages.admin.role_self'));

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    /**
     * Permissions are inert while somebody is an admin, so they are kept rather
     * than cleared — a promotion that is later undone restores the exact grants
     * that editor had, instead of silently resetting them to the defaults.
     */
    public function test_a_promotion_preserves_the_permissions_a_later_demotion_restores(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $tuned = Permission::DEFAULTS;
        $tuned['settings']['view'] = true;
        $tuned['orders']['view'] = false;

        $editor = User::factory()->create(['role' => 'editor']);
        $editor->forceFill(['permissions' => $tuned])->save();

        $this->actingAs($admin)->put("/admin/users/{$editor->id}/role", ['role' => 'admin']);
        $this->actingAs($admin)->put("/admin/users/{$editor->id}/role", ['role' => 'editor']);

        $restored = $editor->fresh();
        $this->assertTrue($restored->isEditor());
        $this->actingAs($restored)->get('/admin/settings')->assertOk();      // still granted
        $this->actingAs($restored)->get('/admin/orders')->assertForbidden(); // still revoked
    }

    public function test_editors_cannot_change_roles(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $target = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->put("/admin/users/{$target->id}/role", ['role' => 'admin'])->assertForbidden();
        $this->assertTrue($target->fresh()->isEditor());
    }

    /**
     * Removal stays editor-only, but a departed admin is no longer a shell job:
     * demote first, then remove.
     */
    public function test_a_departed_admin_can_be_demoted_then_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $leaver = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->delete("/admin/users/{$leaver->id}")->assertForbidden();

        $this->actingAs($admin)->put("/admin/users/{$leaver->id}/role", ['role' => 'editor'])->assertSessionHas('success');
        $this->actingAs($admin)->delete("/admin/users/{$leaver->id}")->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $leaver->id]);
    }

    public function test_admin_grants_and_revokes_editor_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $perms = Permission::DEFAULTS;
        $perms['settings']['view'] = true;  // grant
        $perms['orders']['view'] = false;   // revoke

        $this->actingAs($admin)->put("/admin/users/{$editor->id}/permissions", ['permissions' => $perms])
            ->assertSessionHas('success');

        $this->actingAs($editor->fresh())->get('/admin/settings')->assertOk();     // now allowed
        $this->actingAs($editor->fresh())->get('/admin/orders')->assertForbidden(); // now hidden
    }

    public function test_editors_cannot_manage_other_staff(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $target = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->put("/admin/users/{$target->id}/permissions", ['permissions' => []])->assertForbidden();
        $this->actingAs($editor)->delete("/admin/users/{$target->id}")->assertForbidden();
    }

    public function test_admin_permissions_are_not_editable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put("/admin/users/{$otherAdmin->id}/permissions", ['permissions' => Permission::DEFAULTS])
            ->assertForbidden();
    }

    public function test_resolved_permissions_are_shared_to_the_frontend(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/admin/orders')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.orders.view', true)
                ->where('auth.permissions.settings.view', false));
    }

    /**
     * A section added to the SCHEMA after an editor's permissions were last
     * saved is simply absent from their stored array. That row must fall back
     * to the section's default rather than reading as a denial — otherwise
     * every existing editor needs a manual re-grant each time the panel gains
     * a page (which is what happened when product_requests, then
     * contact_messages, were added).
     */
    public function test_a_section_added_after_the_last_save_falls_back_to_its_default(): void
    {
        $stored = Permission::DEFAULTS;
        unset($stored['contact_messages']); // permissions saved before the section existed

        $editor = User::factory()->create(['role' => 'editor']);
        $editor->forceFill(['permissions' => $stored])->save();

        $this->actingAs($editor->fresh())->get('/admin/contact-messages')->assertOk();
    }

    /**
     * The counterpart: the fallback must never resurrect a permission an admin
     * deliberately switched off, since the grid stores every action explicitly.
     */
    public function test_an_explicit_revocation_still_beats_the_default(): void
    {
        $stored = Permission::DEFAULTS;
        $stored['contact_messages']['view'] = false;

        $editor = User::factory()->create(['role' => 'editor']);
        $editor->forceFill(['permissions' => $stored])->save();

        $this->actingAs($editor->fresh())->get('/admin/contact-messages')->assertForbidden();
    }

    /**
     * The sidebar renders from resolvedPermissions() while the route is gated by
     * hasPermission(). If those two ever disagree, staff get a visible menu entry
     * that 403s on click — so pin that they answer identically for a stale row.
     */
    public function test_the_sidebar_and_the_route_gate_agree_for_a_stale_permission_row(): void
    {
        $stored = Permission::DEFAULTS;
        unset($stored['contact_messages']);

        $editor = User::factory()->create(['role' => 'editor']);
        $editor->forceFill(['permissions' => $stored])->save();
        $editor = $editor->fresh();

        $this->assertSame(
            $editor->resolvedPermissions()['contact_messages']['view'],
            $editor->hasPermission('contact_messages.view'),
        );
    }
}
