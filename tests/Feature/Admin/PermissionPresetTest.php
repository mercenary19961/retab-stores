<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionPresetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 🔴 The property that makes presets safe: EVERY section in the schema must be
     * present in the expanded map, explicitly true or false.
     *
     * `resolvedPermissions()` merges stored grants over DEFAULTS, so a section the
     * preset simply omitted would fall back to its default — which for most
     * sections is `view => true`. An omitted key therefore GRANTS where the preset
     * means to deny.
     */
    public function test_every_preset_covers_every_section_and_action(): void
    {
        foreach (['operations', 'catalogue', 'manager', 'full'] as $name) {
            $map = Permission::preset($name);

            $this->assertSame(array_keys(Permission::SCHEMA), array_keys($map), "preset {$name} is missing a section");

            foreach (Permission::SCHEMA as $section => $actions) {
                $this->assertSame($actions, array_keys($map[$section]), "preset {$name} is missing an action on {$section}");
            }
        }
    }

    public function test_a_preset_grants_only_the_sections_it_names(): void
    {
        $map = Permission::preset('catalogue');

        $this->assertTrue($map['products']['edit']);
        // Orders is not a catalogue section — and must be denied, not absent.
        $this->assertArrayHasKey('orders', $map);
        $this->assertFalse($map['orders']['view']);
        $this->assertFalse($map['settings']['edit']);
    }

    public function test_view_only_grants_view_and_nothing_else(): void
    {
        $map = Permission::preset('full', viewOnly: true);

        foreach (Permission::SCHEMA as $section => $actions) {
            foreach ($actions as $action) {
                $this->assertSame($action === 'view', $map[$section][$action], "{$section}.{$action}");
            }
        }
    }

    public function test_full_grants_everything(): void
    {
        foreach (Permission::preset('full') as $section => $actions) {
            foreach ($actions as $action => $granted) {
                $this->assertTrue($granted, "{$section}.{$action}");
            }
        }
    }

    /** An unknown name denies everything rather than throwing or granting. */
    public function test_an_unknown_preset_denies_everything(): void
    {
        foreach (Permission::preset('does-not-exist') as $actions) {
            foreach ($actions as $granted) {
                $this->assertFalse($granted);
            }
        }
    }

    /** A preset applied to a real editor produces the access it claims. */
    public function test_a_preset_drives_the_real_permission_check(): void
    {
        $editor = User::forceCreate([
            'name' => 'Editor',
            'email' => 'preset-editor@retab.test',
            'password' => 'x',
            'role' => 'editor',
            'permissions' => Permission::preset('operations'),
        ]);

        $this->assertTrue($editor->hasPermission('orders.manage'));
        $this->assertTrue($editor->hasPermission('returns.resolve'));
        $this->assertFalse($editor->hasPermission('products.edit'));
        $this->assertFalse($editor->hasPermission('settings.edit'));
    }

    /** The page ships the presets the grid renders. */
    public function test_the_staff_page_ships_presets(): void
    {
        $admin = User::forceCreate([
            'name' => 'Admin',
            'email' => 'preset-admin@retab.test',
            'password' => 'x',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertInertia(fn ($page) => $page
                ->has('presets.operations')
                ->has('presets.catalogue')
                ->has('presets.manager')
                ->has('presets.readonly')
                ->has('presets.full')
                ->where('presets.readonly.orders.view', true)
                ->where('presets.readonly.orders.manage', false)
            );
    }
}
