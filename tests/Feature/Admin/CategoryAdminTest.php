<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreEvent;
use App\Models\User;
use App\Services\ChangeLog\ChangeLogService;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryAdminTest extends TestCase
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
            'name_ar' => 'قسم '.uniqid(),
            'slug' => 'c-'.uniqid(),
            'sort_order' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function product(Category $category): Product
    {
        return Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري فاخر',
            'slug' => 'p-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()),
            'price' => 50,
            'stock' => 10,
            'is_active' => false,
        ]);
    }

    /** A valid dialog submission, over which each test changes what it is about. */
    private function payload(array $overrides = []): array
    {
        return array_merge(['name_ar' => 'التمور الملكية', 'name_en' => 'Royal Dates', 'slug' => '', 'parent_id' => '', 'is_active' => '1'], $overrides);
    }

    public function test_the_page_lists_every_category_with_why_it_cannot_be_deleted(): void
    {
        $group = $this->category(['slug' => 'group']);
        $leaf = $this->category(['slug' => 'leaf', 'parent_id' => $group->id]);
        $this->product($leaf);
        $empty = $this->category(['slug' => 'empty']);
        $offers = StoreEvent::offersCategory();

        $response = $this->actingAs($this->admin())->get('/admin/categories')->assertOk();

        $rows = collect($response->viewData('page')['props']['categories'])->keyBy('slug');
        $this->assertSame('category_has_children', $rows['group']['delete_blocker']);
        // Products never block a delete any more; they are moved or left uncategorized.
        $this->assertNull($rows['leaf']['delete_blocker']);
        $this->assertSame('category_protected', $rows[StoreEvent::OFFERS_CATEGORY_SLUG]['delete_blocker']);
        $this->assertNull($rows['empty']['delete_blocker']);
        $this->assertSame(1, $rows['leaf']['products_count']);
        $this->assertSame(1, $rows['group']['children_count']);
    }

    public function test_creating_derives_the_slug_from_the_english_name_and_goes_last(): void
    {
        // The migrations seed Special Offers (sort 90), so read the real maximum.
        $this->category(['sort_order' => 700]);

        $this->actingAs($this->admin())->post('/admin/categories', $this->payload())->assertSessionHasNoErrors();

        $created = Category::where('name_ar', 'التمور الملكية')->firstOrFail();
        $this->assertSame('royal-dates', $created->slug);
        $this->assertSame(701, $created->sort_order);
        $this->assertTrue(ActivityLog::where('subject_type', Category::class)->where('subject_id', $created->id)->where('action', ActivityLog::ACTION_CREATED)->exists());
    }

    public function test_an_arabic_only_name_gets_an_arabic_slug_made_unique(): void
    {
        $this->category(['slug' => 'التمور-الملكية']);

        $this->actingAs($this->admin())->post('/admin/categories', $this->payload(['name_en' => '']))->assertSessionHasNoErrors();

        $this->assertTrue(Category::where('slug', 'التمور-الملكية-2')->exists());
    }

    public function test_editing_with_an_empty_slug_keeps_the_current_link(): void
    {
        $category = $this->category(['slug' => 'rusks']);

        $this->actingAs($this->admin())
            ->post("/admin/categories/{$category->id}", $this->payload(['_method' => 'put', 'name_en' => 'Toast']))
            ->assertSessionHasNoErrors();

        $this->assertSame('rusks', $category->fresh()->slug);
        $this->assertSame('Toast', $category->fresh()->name_en);
    }

    /**
     * 🔴 Deleting a category never deletes its products — trashed ones included,
     * which are still restorable from the change log. They are left without a
     * category, where they stay on sale.
     */
    public function test_deleting_a_category_leaves_its_products_without_one(): void
    {
        $category = $this->category();
        $kept = $this->product($category);
        $trashed = $this->product($category);
        $trashed->delete();

        $this->actingAs($this->admin())->delete("/admin/categories/{$category->id}")
            ->assertSessionHas('success', __('messages.admin.category_deleted_orphaned', ['count' => 1]));

        $this->assertModelMissing($category);
        $this->assertNull($kept->fresh()->category_id);
        $this->assertNull(Product::withTrashed()->find($trashed->id)->category_id);
    }

    public function test_deleting_can_move_the_products_to_another_category(): void
    {
        $category = $this->category();
        $target = $this->category(['name_ar' => 'البوكسات']);
        $product = $this->product($category);

        $this->actingAs($this->admin())->delete("/admin/categories/{$category->id}", ['move_to' => $target->id])
            ->assertSessionHas('success', __('messages.admin.category_deleted_moved', ['count' => 1, 'name' => 'البوكسات']));

        $this->assertSame($target->id, $product->fresh()->category_id);
    }

    /** A menu group holds subcategories, never products, so it cannot receive them. */
    public function test_products_cannot_be_moved_into_a_group_on_delete(): void
    {
        $category = $this->category();
        $group = $this->category();
        $this->category(['parent_id' => $group->id]);
        $product = $this->product($category);

        $this->actingAs($this->admin())->delete("/admin/categories/{$category->id}", ['move_to' => $group->id])
            ->assertSessionHas('error', __('messages.admin.category_move_target_invalid'));

        $this->assertModelExists($category);
        $this->assertSame($category->id, $product->fresh()->category_id);
    }

    /**
     * The foreign key itself no longer cascades, so a delete from ANY path
     * (tinker, a seeder) leaves the products standing.
     */
    public function test_the_foreign_key_nulls_instead_of_cascading(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $category->delete();

        $this->assertNotNull($product->fresh());
        $this->assertNull($product->fresh()->category_id);
    }

    public function test_a_group_with_subcategories_and_the_offers_bucket_cannot_be_deleted(): void
    {
        $group = $this->category();
        $this->category(['parent_id' => $group->id]);
        $offers = StoreEvent::offersCategory();
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/admin/categories/{$group->id}")->assertSessionHas('error', __('messages.admin.category_has_children'));
        $this->actingAs($admin)->delete("/admin/categories/{$offers->id}")->assertSessionHas('error', __('messages.admin.category_protected'));

        $this->assertModelExists($group);
        $this->assertModelExists($offers);
    }

    public function test_an_empty_category_is_deleted_along_with_its_uploaded_image(): void
    {
        Storage::fake(Media::disk());
        $path = UploadedFile::fake()->image('tile.png')->store(Category::IMAGE_DIR, Media::disk());
        $category = $this->category(['image' => $path]);

        $this->actingAs($this->admin())->delete("/admin/categories/{$category->id}")->assertSessionHas('success');

        $this->assertModelMissing($category);
        Storage::disk(Media::disk())->assertMissing($path);
    }

    public function test_the_shape_stays_two_levels_and_leaves_hold_products_only(): void
    {
        $group = $this->category();
        $child = $this->category(['parent_id' => $group->id]);
        $withProducts = $this->category();
        $this->product($withProducts);
        $admin = $this->admin();

        // No third level.
        $this->actingAs($admin)->post('/admin/categories', $this->payload(['parent_id' => $child->id]))
            ->assertSessionHasErrors(['parent_id' => __('messages.admin.category_parent_depth')]);
        // A category holding products cannot become a group.
        $this->actingAs($admin)->post('/admin/categories', $this->payload(['parent_id' => $withProducts->id]))
            ->assertSessionHasErrors(['parent_id' => __('messages.admin.category_parent_has_products')]);
        // A group cannot be moved under something.
        $other = $this->category();
        $this->actingAs($admin)->post("/admin/categories/{$group->id}", $this->payload(['_method' => 'put', 'parent_id' => $other->id]))
            ->assertSessionHasErrors(['parent_id' => __('messages.admin.category_group_stays_top')]);
        // Nor under itself.
        $this->actingAs($admin)->post("/admin/categories/{$other->id}", $this->payload(['_method' => 'put', 'parent_id' => $other->id]))
            ->assertSessionHasErrors(['parent_id' => __('messages.admin.category_parent_self')]);

        $this->assertNull($group->fresh()->parent_id);
    }

    /** Store events find the bucket BY SLUG, so renaming the link would orphan it. */
    public function test_the_offers_bucket_keeps_its_slug_and_level_but_can_be_renamed(): void
    {
        $offers = StoreEvent::offersCategory();
        $group = $this->category();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/categories/{$offers->id}", $this->payload(['_method' => 'put', 'slug' => 'deals']))
            ->assertSessionHasErrors(['slug' => __('messages.admin.category_slug_locked')]);
        $this->actingAs($admin)->post("/admin/categories/{$offers->id}", $this->payload(['_method' => 'put', 'parent_id' => $group->id]))
            ->assertSessionHasErrors(['parent_id' => __('messages.admin.category_offers_top_level')]);

        $this->actingAs($admin)->post("/admin/categories/{$offers->id}", $this->payload(['_method' => 'put', 'name_ar' => 'عروض المواسم']))
            ->assertSessionHasNoErrors();

        $this->assertSame(StoreEvent::OFFERS_CATEGORY_SLUG, $offers->fresh()->slug);
        $this->assertSame('عروض المواسم', $offers->fresh()->name_ar);
    }

    /**
     * 🔑 The homepage tiles order by sort_order across ALL categories, so moving
     * within one group must swap values, not renumber the group from zero.
     */
    public function test_moving_swaps_with_the_sibling_and_leaves_other_groups_alone(): void
    {
        $dates = $this->category(['sort_order' => 0]);
        $a = $this->category(['parent_id' => $dates->id, 'sort_order' => 0]);
        $b = $this->category(['parent_id' => $dates->id, 'sort_order' => 1]);
        $gifts = $this->category(['sort_order' => 1]);
        $c = $this->category(['parent_id' => $gifts->id, 'sort_order' => 3]);
        $d = $this->category(['parent_id' => $gifts->id, 'sort_order' => 4]);

        $this->actingAs($this->admin())->patch("/admin/categories/{$d->id}/move", ['direction' => 'up']);

        $this->assertSame([4, 3], [$c->fresh()->sort_order, $d->fresh()->sort_order]);
        $this->assertSame([0, 1], [$a->fresh()->sort_order, $b->fresh()->sort_order]);
    }

    public function test_moving_past_the_end_does_nothing(): void
    {
        $only = $this->category(['sort_order' => 5]);

        $this->actingAs($this->admin())->patch("/admin/categories/{$only->id}/move", ['direction' => 'up'])->assertRedirect();

        $this->assertSame(5, $only->fresh()->sort_order);
    }

    public function test_toggling_hides_and_logs_a_revertable_edit(): void
    {
        $category = $this->category();

        $this->actingAs($this->admin())->patch("/admin/categories/{$category->id}/toggle")
            ->assertSessionHas('success', __('messages.admin.category_hidden'));

        $this->assertFalse($category->fresh()->is_active);
        $log = ActivityLog::where('subject_type', Category::class)->where('action', ActivityLog::ACTION_UPDATED)->firstOrFail();
        $this->assertTrue(app(ChangeLogService::class)->revertable($log));
    }

    /** Undoing a create would delete the row and cascade to its products. */
    public function test_a_create_is_audit_only_in_the_change_log(): void
    {
        $this->actingAs($this->admin())->post('/admin/categories', $this->payload());

        $log = ActivityLog::where('subject_type', Category::class)->where('action', ActivityLog::ACTION_CREATED)->firstOrFail();
        $this->assertFalse(app(ChangeLogService::class)->revertable($log));
    }

    /**
     * An uploaded tile is a media-disk key, a shipped one a web path. The
     * homepage must get a working URL for both.
     */
    public function test_an_uploaded_tile_reaches_the_homepage_as_a_media_url(): void
    {
        Storage::fake(Media::disk());
        $shipped = $this->category(['slug' => 'shipped', 'image' => '/images/categories/rusks.webp', 'sort_order' => 0]);
        $leaf = $this->category(['slug' => 'uploaded', 'sort_order' => 1]);

        $this->actingAs($this->admin())
            ->post("/admin/categories/{$leaf->id}", $this->payload(['_method' => 'put', 'image' => UploadedFile::fake()->image('tile.png', 400, 400)]))
            ->assertSessionHasNoErrors();

        $stored = $leaf->fresh()->image;
        $this->assertStringStartsWith(Category::IMAGE_DIR.'/', $stored);

        $tiles = collect($this->get('/')->viewData('page')['props']['featuredCategories'])->keyBy('slug');
        $this->assertSame('/images/categories/rusks.webp', $tiles['shipped']['image']);
        $this->assertStringContainsString('categories/', $tiles['uploaded']['image']);
        $this->assertStringNotContainsString('/images/', $tiles['uploaded']['image']);
    }

    public function test_remove_image_clears_the_tile(): void
    {
        $category = $this->category(['image' => '/images/categories/rusks.webp']);

        $this->actingAs($this->admin())
            ->post("/admin/categories/{$category->id}", $this->payload(['_method' => 'put', 'remove_image' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertNull($category->fresh()->image);
    }

    /**
     * A leaf is "has no subcategories", not "has a parent" — so a top-level
     * category with no children (Special Offers, or a brand-new one) can be
     * picked in the product form, while a menu group cannot.
     */
    public function test_the_product_form_offers_every_leaf_including_top_level_ones(): void
    {
        $group = $this->category();
        $child = $this->category(['parent_id' => $group->id]);
        $topLeaf = StoreEvent::offersCategory();

        $ids = collect($this->actingAs($this->admin())->get('/admin/products/create')->viewData('page')['props']['categories'])->pluck('id');

        $this->assertTrue($ids->contains($child->id));
        $this->assertTrue($ids->contains($topLeaf->id));
        $this->assertFalse($ids->contains($group->id));
    }

    public function test_an_editor_with_stale_stored_permissions_inherits_the_new_section(): void
    {
        // A permission grid saved before this section existed has no `categories` key.
        $editor = $this->editor(['products' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false]]);

        $this->actingAs($editor)->get('/admin/categories')->assertOk();
        $this->actingAs($editor)->post('/admin/categories', $this->payload())->assertSessionHasNoErrors();
    }

    public function test_managing_needs_the_manage_permission(): void
    {
        $category = $this->category();
        $editor = $this->editor(['categories' => ['view' => true, 'manage' => false]]);

        $this->actingAs($editor)->get('/admin/categories')->assertOk();
        $this->actingAs($editor)->post('/admin/categories', $this->payload())->assertForbidden();
        $this->actingAs($editor)->patch("/admin/categories/{$category->id}/toggle")->assertForbidden();
        $this->actingAs($editor)->delete("/admin/categories/{$category->id}")->assertForbidden();

        $this->assertModelExists($category);
    }
}
