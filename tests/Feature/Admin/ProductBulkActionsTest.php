<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'a'.uniqid().'@test.com',
            'password' => bcrypt('x'), 'role' => 'admin',
        ]);
    }

    private function editor(?array $permissions = null): User
    {
        return User::forceCreate([
            'name' => 'Editor', 'email' => 'e'.uniqid().'@test.com',
            'password' => bcrypt('x'), 'role' => 'editor', 'permissions' => $permissions,
        ]);
    }

    private function category(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name_ar' => 'قسم '.uniqid(), 'slug' => 'c-'.uniqid(), 'is_active' => true,
        ], $overrides));
    }

    private function product(?Category $category, array $overrides = [], bool $withImage = true): Product
    {
        $product = Product::create(array_merge([
            'category_id' => $category?->id,
            'name_ar' => 'سكري فاخر',
            'slug' => 'p-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()),
            'price' => 50,
            'stock' => 10,
            'is_active' => false,
        ], $overrides));

        if ($withImage) {
            ProductImage::create(['product_id' => $product->id, 'path' => 'products/x.jpg', 'is_primary' => true]);
        }

        return $product;
    }

    public function test_moving_files_every_selected_product_and_logs_each(): void
    {
        $from = $this->category();
        $to = $this->category(['name_ar' => 'البوكسات']);
        $a = $this->product($from);
        $b = $this->product($from);
        $already = $this->product($to);

        $this->actingAs($this->admin())
            ->post('/admin/products/bulk/category', ['ids' => [$a->id, $b->id, $already->id], 'category_id' => $to->id])
            ->assertSessionHas('success', __('messages.admin.bulk_moved', ['count' => 2, 'name' => 'البوكسات']));

        $this->assertSame([$to->id, $to->id], [$a->fresh()->category_id, $b->fresh()->category_id]);
        // One revertable entry per product that actually moved.
        $this->assertSame(2, ActivityLog::where('subject_type', Product::class)->where('action', ActivityLog::ACTION_UPDATED)->count());
    }

    public function test_moving_to_no_category_leaves_them_findable_under_the_filter(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $this->actingAs($this->admin())
            ->post('/admin/products/bulk/category', ['ids' => [$product->id], 'category_id' => null])
            ->assertSessionHas('success');

        $this->assertNull($product->fresh()->category_id);

        $this->actingAs($this->admin())->get('/admin/products?category=none')
            ->assertInertia(fn ($page) => $page
                ->where('filters.category', 'none')
                ->where('uncategorizedCount', 1)
                ->has('products.data', 1)
                ->where('products.data.0.id', $product->id));
    }

    public function test_products_cannot_be_moved_into_a_menu_group(): void
    {
        $group = $this->category();
        $this->category(['parent_id' => $group->id]);
        $leaf = $this->category();
        $product = $this->product($leaf);

        $this->actingAs($this->admin())
            ->post('/admin/products/bulk/category', ['ids' => [$product->id], 'category_id' => $group->id])
            ->assertSessionHas('error', __('messages.admin.category_move_target_invalid'));

        $this->assertSame($leaf->id, $product->fresh()->category_id);
    }

    /** Showing obeys the publish guard: an incomplete product stays hidden and is counted. */
    public function test_showing_skips_products_that_cannot_go_live(): void
    {
        $category = $this->category();
        $complete = $this->product($category);
        $noImage = $this->product($category, [], withImage: false);

        $this->actingAs($this->admin())
            ->post('/admin/products/bulk/visibility', ['ids' => [$complete->id, $noImage->id], 'visible' => true])
            ->assertSessionHas('success', __('messages.admin.bulk_shown', ['count' => 1]).' '.__('messages.admin.bulk_show_blocked', ['count' => 1]));

        $this->assertTrue($complete->fresh()->is_active);
        $this->assertFalse($noImage->fresh()->is_active);
    }

    public function test_hiding_takes_them_off_the_store(): void
    {
        $category = $this->category();
        $a = $this->product($category, ['is_active' => true]);
        $b = $this->product($category, ['is_active' => true]);

        $this->actingAs($this->admin())
            ->post('/admin/products/bulk/visibility', ['ids' => [$a->id, $b->id], 'visible' => false])
            ->assertSessionHas('success', __('messages.admin.bulk_hidden', ['count' => 2]));

        $this->assertFalse($a->fresh()->is_active);
        $this->assertFalse($b->fresh()->is_active);
    }

    public function test_deleting_soft_deletes_and_stays_restorable(): void
    {
        $category = $this->category();
        $a = $this->product($category);
        $b = $this->product($category);

        $this->actingAs($this->admin())
            ->post('/admin/products/bulk/destroy', ['ids' => [$a->id, $b->id]])
            ->assertSessionHas('success', __('messages.admin.bulk_products_deleted', ['count' => 2]));

        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
        $this->assertSame(2, ActivityLog::where('subject_type', Product::class)->where('action', ActivityLog::ACTION_DELETED)->count());
    }

    public function test_bulk_actions_follow_the_single_row_permissions(): void
    {
        $product = $this->product($this->category());
        // Default editor: may edit products, may NOT delete them.
        $editor = $this->editor();

        $this->actingAs($editor)->post('/admin/products/bulk/visibility', ['ids' => [$product->id], 'visible' => false])->assertRedirect();
        $this->actingAs($editor)->post('/admin/products/bulk/destroy', ['ids' => [$product->id]])->assertForbidden();

        $readOnly = $this->editor(['products' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false]]);
        $this->actingAs($readOnly)->post('/admin/products/bulk/category', ['ids' => [$product->id], 'category_id' => null])->assertForbidden();

        $this->assertNotSoftDeleted($product);
    }

    /**
     * 🔑 The point of leaving products uncategorized instead of deleting them: a
     * visible one is still on sale — in the unfiltered catalogue, in search, and
     * on its own page.
     */
    public function test_an_uncategorized_product_is_still_found_on_the_store(): void
    {
        $category = $this->category();
        $product = $this->product($category, ['is_active' => true, 'name_ar' => 'تمر يتيم']);

        $this->actingAs($this->admin())->delete("/admin/categories/{$category->id}");
        $this->assertNull($product->fresh()->category_id);
        auth()->logout();

        $this->get('/shop')->assertOk()->assertInertia(fn ($page) => $page->where('products.data.0.id', $product->id));
        $this->assertContains($product->id, collect($this->get('/shop/search-index')->json('products'))->pluck('id'));
        $this->get("/products/{$product->slug}")->assertOk()->assertInertia(fn ($page) => $page->where('product.category', null));
    }

    /** A product left without a category stays editable, and keeps having none. */
    public function test_an_uncategorized_product_can_be_saved_without_a_category(): void
    {
        $product = $this->product(null, ['price' => 20]);

        $this->actingAs($this->admin())->get("/admin/products/{$product->id}/detail")
            ->assertOk()
            ->assertJsonPath('product.category_id', null);
    }
}
