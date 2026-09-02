<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * After creating, editing or deleting a product the admin should land back on
 * the list AS THEY LEFT IT, not on an unfiltered page 1.
 */
class ProductListViewMemoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'a@retab.test', 'password' => bcrypt('x'), 'role' => 'admin',
        ]);
        $this->category = Category::create(['name_ar' => 'تمور', 'slug' => 'dates']);
    }

    private function product(string $sku = 'RTB-1'): Product
    {
        return Product::create([
            'category_id' => $this->category->id, 'name_ar' => 'خلاص', 'slug' => 'slug-'.$sku,
            'price' => 10, 'sku' => $sku, 'stock' => 5,
        ]);
    }

    public function test_deleting_returns_to_the_same_filtered_view(): void
    {
        $product = $this->product();
        $filters = ['status' => 'draft', 'category' => $this->category->id, 'search' => 'خلاص'];

        $this->actingAs($this->admin)->get('/admin/products?'.http_build_query($filters))->assertSuccessful();

        $response = $this->actingAs($this->admin)->delete("/admin/products/{$product->id}");

        $target = $response->headers->get('Location');
        foreach ($filters as $key => $value) {
            $this->assertStringContainsString($key.'='.rawurlencode((string) $value), rawurldecode($target) === $target ? $target : $target,
                "the redirect dropped the {$key} filter");
        }
    }

    public function test_editing_returns_to_the_same_filtered_view(): void
    {
        $product = $this->product();
        $this->actingAs($this->admin)->get('/admin/products?status=draft')->assertSuccessful();

        $response = $this->actingAs($this->admin)->put("/admin/products/{$product->id}", [
            'category_id' => $this->category->id,
            'name_ar' => 'خلاص معدّل',
            'price' => 12,
            'sku' => 'RTB-1',
            'stock' => 5,
        ]);

        $this->assertStringContainsString('status=draft', $response->headers->get('Location'));
    }

    public function test_arriving_with_no_filters_forgets_the_previous_ones(): void
    {
        // ⚠️ The memory is a mirror of the LAST list view, not a sticky
        // preference — otherwise clicking "Products" in the sidebar would show a
        // filtered list for reasons the admin cannot see.
        $product = $this->product();
        $this->actingAs($this->admin)->get('/admin/products?status=draft')->assertSuccessful();
        $this->actingAs($this->admin)->get('/admin/products')->assertSuccessful();

        $response = $this->actingAs($this->admin)->delete("/admin/products/{$product->id}");

        $this->assertStringNotContainsString('status=', $response->headers->get('Location'));
    }

    public function test_only_whitelisted_keys_are_remembered(): void
    {
        // A stray query param must never be replayed into a later redirect.
        $product = $this->product();
        $this->actingAs($this->admin)->get('/admin/products?status=draft&evil=1')->assertSuccessful();

        $response = $this->actingAs($this->admin)->delete("/admin/products/{$product->id}");

        $this->assertStringContainsString('status=draft', $response->headers->get('Location'));
        $this->assertStringNotContainsString('evil', $response->headers->get('Location'));
    }
}
