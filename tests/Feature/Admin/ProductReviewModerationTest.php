<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewHelpfulVote;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductReviewModerationTest extends TestCase
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

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'التمور', 'is_active' => true])->id,
            'name_ar' => 'تمر سكري فاخر',
            'name_en' => 'Premium Sukkari',
            'slug' => 'sukkari-'.uniqid(),
            'sku' => 'RTB-'.random_int(1000, 9999),
            'price' => 55.00,
            'stock' => 10,
            'is_active' => true,
        ], $overrides));
    }

    private function review(Product $product, array $overrides = []): Review
    {
        return Review::create(array_merge([
            'product_id' => $product->id,
            'user_id' => $this->editor()->id,
            'rating' => 5,
            'body' => 'تمور ممتازة ووصلت بسرعة.',
            'language' => 'ar',
            'is_approved' => true,
        ], $overrides));
    }

    public function test_the_page_lists_reviews_with_their_body_and_status(): void
    {
        $product = $this->product();
        $this->review($product, ['body' => 'وصلت سريعًا والتغليف ممتاز.']);

        $this->actingAs($this->admin())
            ->get('/admin/product-reviews')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/product-reviews/index')
                ->has('reviews.data', 1)
                ->where('reviews.data.0.body', 'وصلت سريعًا والتغليف ممتاز.')
                ->where('reviews.data.0.approved', true)
                ->where('reviews.data.0.rating', 5)
            );
    }

    public function test_hiding_a_review_removes_it_from_the_storefront(): void
    {
        $product = $this->product();
        $review = $this->review($product);

        // Visible on the product page to begin with.
        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('reviews.items', 1)->where('reviews.summary.count', 1));

        $this->actingAs($this->admin())
            ->patch("/admin/product-reviews/{$review->id}/toggle")
            ->assertRedirect();

        $this->assertFalse($review->fresh()->is_approved);

        // 🔑 The point of the whole feature: hiding it in the panel actually takes
        // it off the public page, rather than only flipping a flag nothing reads.
        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('reviews.items', 0)->where('reviews.summary.count', 0));
    }

    public function test_the_toggle_restores_a_hidden_review(): void
    {
        $review = $this->review($this->product(), ['is_approved' => false]);

        $this->actingAs($this->admin())->patch("/admin/product-reviews/{$review->id}/toggle");

        $this->assertTrue($review->fresh()->is_approved);
    }

    public function test_deleting_a_review_also_clears_its_helpful_votes(): void
    {
        $review = $this->review($this->product());
        ReviewHelpfulVote::create(['review_id' => $review->id, 'user_id' => $this->editor()->id]);

        $this->actingAs($this->admin())
            ->delete("/admin/product-reviews/{$review->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        // Orphaned votes would point at a review id that no longer exists.
        $this->assertDatabaseMissing('review_helpful_votes', ['review_id' => $review->id]);
    }

    public function test_the_status_filter_separates_published_from_hidden(): void
    {
        $product = $this->product();
        $this->review($product, ['is_approved' => true]);
        $this->review($product, ['is_approved' => false, 'user_id' => $this->editor()->id]);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/product-reviews?status=published')
            ->assertInertia(fn (Assert $page) => $page->has('reviews.data', 1)->where('reviews.data.0.approved', true));

        $this->actingAs($admin)->get('/admin/product-reviews?status=hidden')
            ->assertInertia(fn (Assert $page) => $page->has('reviews.data', 1)->where('reviews.data.0.approved', false));
    }

    public function test_the_average_counts_published_reviews_only(): void
    {
        $product = $this->product();
        $this->review($product, ['rating' => 5, 'is_approved' => true]);
        // A hidden 1-star must not drag down the number, because the storefront
        // never showed it — the two figures have to agree.
        $this->review($product, ['rating' => 1, 'is_approved' => false, 'user_id' => $this->editor()->id]);

        $this->actingAs($this->admin())
            ->get('/admin/product-reviews')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.average', 5)
                ->where('stats.total', 2)
                ->where('stats.published', 1)
                ->where('stats.hidden', 1)
            );
    }

    public function test_search_matches_the_product_name(): void
    {
        $this->review($this->product(['name_ar' => 'تمر عجوة', 'name_en' => 'Ajwa Dates']));
        $this->review($this->product(['name_ar' => 'تمر خلاص', 'name_en' => 'Khalas Dates']));

        $this->actingAs($this->admin())
            ->get('/admin/product-reviews?q=Ajwa')
            ->assertInertia(fn (Assert $page) => $page
                ->has('reviews.data', 1)
                ->where('reviews.data.0.product.name_en', 'Ajwa Dates')
            );
    }

    public function test_an_editor_without_the_permission_is_refused(): void
    {
        $review = $this->review($this->product());

        // An explicit empty grant, so this is a genuine denial rather than a
        // fall-through to DEFAULTS (which do grant this section).
        $denied = $this->editor(Permission::preset('operations'));

        $this->actingAs($denied)->get('/admin/product-reviews')->assertForbidden();
        $this->actingAs($denied)->patch("/admin/product-reviews/{$review->id}/toggle")->assertForbidden();
        $this->assertTrue($review->fresh()->is_approved);
    }

    public function test_an_editor_with_stale_permissions_inherits_the_new_section(): void
    {
        // A row saved before product_reviews existed simply has no key for it.
        // resolvedPermissions() must fall back to DEFAULTS rather than denying,
        // which is the 2026-08-15 hasPermission fix still holding.
        $stale = $this->editor(['orders' => ['view' => true, 'manage' => true, 'export' => false]]);

        $this->actingAs($stale)->get('/admin/product-reviews')->assertOk();
    }

    public function test_guests_and_customers_cannot_reach_the_page(): void
    {
        $this->get('/admin/product-reviews')->assertRedirect('/login');

        $customer = User::forceCreate([
            'name' => 'Customer', 'email' => 'c'.uniqid().'@test.com',
            'password' => bcrypt('x'), 'role' => 'customer',
        ]);
        $this->actingAs($customer)->get('/admin/product-reviews')->assertForbidden();
    }

    public function test_every_permission_preset_covers_the_new_section(): void
    {
        // A section missing from a preset would fall through to DEFAULTS, which
        // GRANT it — so an omission silently grants instead of denying.
        foreach (array_keys(Permission::PRESETS) as $name) {
            $map = Permission::preset($name);
            $this->assertArrayHasKey('product_reviews', $map, "preset {$name} omits product_reviews");
            $this->assertArrayHasKey('manage', $map['product_reviews']);
        }
    }
}
