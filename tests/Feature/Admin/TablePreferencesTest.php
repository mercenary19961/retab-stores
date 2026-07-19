<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TablePreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_save_table_column_widths(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);

        $this->actingAs($staff)->putJson('/admin/preferences/table-widths', [
            'table' => 'products',
            'widths' => ['product' => 320, 'sku' => 100],
        ])->assertNoContent();

        $this->assertSame(320, $staff->fresh()->ui_preferences['tableWidths']['products']['product']);
    }

    public function test_widths_are_validated_and_gated(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        // Below the min width → rejected.
        $this->actingAs($staff)->putJson('/admin/preferences/table-widths', [
            'table' => 'products', 'widths' => ['x' => 5],
        ])->assertStatus(422);

        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->putJson('/admin/preferences/table-widths', [
            'table' => 'products', 'widths' => ['x' => 120],
        ])->assertForbidden();
    }

    public function test_saved_widths_are_shared_to_the_frontend(): void
    {
        $staff = User::factory()->create([
            'role' => 'admin',
            'ui_preferences' => ['tableWidths' => ['products' => ['product' => 333]]],
        ]);

        $this->actingAs($staff)->get('/admin/products')
            ->assertInertia(fn (Assert $page) => $page->where('tablePrefs.products.product', 333));
    }
}
