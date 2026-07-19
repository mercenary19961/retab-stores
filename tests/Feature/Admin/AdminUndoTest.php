<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Per-section "undo last save": a save on a tracked section sets a session
 * pointer (flash toast + persistent per-section) reused by the change-log revert.
 */
class AdminUndoTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function product(Category $c, string $name = 'سكري'): Product
    {
        $product = Product::create([
            'category_id' => $c->id, 'name_ar' => $name, 'slug' => 'p-' . uniqid(),
            'price' => 50, 'sku' => 'SK-' . uniqid(), 'stock' => 100, 'is_active' => true, 'is_featured' => false,
        ]);

        // Products must have an image to be updatable (see ProductController::update).
        $product->images()->create(['path' => 'products/seed.jpg', 'sort_order' => 1, 'is_primary' => true]);

        return $product;
    }

    private function payload(Product $p, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $p->category_id, 'name_ar' => $p->name_ar, 'slug' => $p->slug,
            'price' => 50, 'sku' => $p->sku, 'stock' => 100, 'is_active' => true, 'is_featured' => false,
        ], $overrides);
    }

    public function test_product_save_surfaces_undo_on_index(): void
    {
        $cat = Category::create(['name_ar' => 'تمور', 'slug' => 'c-' . uniqid()]);
        $product = $this->product($cat);
        $staff = $this->staff();

        // A save that actually changes a field.
        $this->actingAs($staff)->put("/admin/products/{$product->id}", $this->payload($product, ['name_ar' => 'خلاص']))
            ->assertRedirect(route('admin.products.index'));

        // The persistent pointer surfaces on the section page.
        $this->actingAs($staff)->get('/admin/products')
            ->assertInertia(fn (Assert $page) => $page
                ->where('undoMeta.section', 'products')
                ->has('undoMeta.id')
                ->has('undoMeta.changes'));
    }

    public function test_dismiss_clears_the_pointer(): void
    {
        $cat = Category::create(['name_ar' => 'تمور', 'slug' => 'c-' . uniqid()]);
        $product = $this->product($cat);
        $staff = $this->staff();

        $this->actingAs($staff)->put("/admin/products/{$product->id}", $this->payload($product, ['name_ar' => 'خلاص']));
        $this->actingAs($staff)->delete('/admin/change-log/undo/products');

        $this->actingAs($staff)->get('/admin/products')
            ->assertInertia(fn (Assert $page) => $page->where('undoMeta', null));
    }

    public function test_undo_reverts_the_change_and_clears_the_pointer(): void
    {
        $cat = Category::create(['name_ar' => 'تمور', 'slug' => 'c-' . uniqid()]);
        $product = $this->product($cat, 'الاسم الأصلي');
        $staff = $this->staff();

        $this->actingAs($staff)->put("/admin/products/{$product->id}", $this->payload($product, ['name_ar' => 'اسم جديد']));
        $log = ActivityLog::where('action', 'updated')->latest('id')->firstOrFail();

        $this->actingAs($staff)->post("/admin/change-log/{$log->id}/revert")->assertSessionHas('success');

        $this->assertSame('الاسم الأصلي', $product->fresh()->name_ar); // reverted
        // Pointer cleared, and the revert entry itself doesn't offer an undo.
        $this->actingAs($staff)->get('/admin/products')
            ->assertInertia(fn (Assert $page) => $page->where('undoMeta', null));
    }

    public function test_customers_never_receive_the_undo_prop(): void
    {
        // Non-staff cannot reach admin pages at all; the shared prop is null for them.
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('undo', null));
    }
}
