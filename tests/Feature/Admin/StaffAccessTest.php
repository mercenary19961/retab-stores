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

    public function test_only_admins_reach_the_staff_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/users')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'editor']))->get('/admin/users')->assertForbidden();
    }

    public function test_admin_creates_an_editor_with_default_permissions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/users', ['name' => 'New Editor', 'email' => 'ed@retab.test', 'password' => 'password123'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'ed@retab.test', 'role' => 'editor']);
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
}
