<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductRequest;
use App\Models\User;
use App\Notifications\ProductRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductRequestTest extends TestCase
{
    use RefreshDatabase;

    private function category(): Category
    {
        return Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'التمور', 'is_active' => true]);
    }

    /** A hidden product surfaced on the store as Coming Soon (request-only). */
    private function comingSoon(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $this->category()->id,
            'name_ar' => 'صفاوي المدينة',
            'slug' => 'safawi-soon',
            'price' => 0,
            'sku' => 'CS-' . uniqid(),
            'stock' => 0,
            'is_active' => false,
            'is_coming_soon' => true,
        ], $overrides));
    }

    private function staff(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'a' . uniqid() . '@test.com', 'password' => bcrypt('x'), 'role' => 'admin']);
    }

    public function test_coming_soon_product_shows_in_the_catalogue_with_its_flag(): void
    {
        $this->comingSoon();

        $this->get('/shop')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('products.data', 1)
                ->where('products.data.0.coming_soon', true),
        );
    }

    public function test_coming_soon_product_page_renders_instead_of_404(): void
    {
        $this->comingSoon(['slug' => 'safawi-page']);

        $this->get('/products/safawi-page')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('shop/product')->where('product.coming_soon', true),
        );
    }

    public function test_a_plain_hidden_product_still_404s(): void
    {
        $this->comingSoon(['slug' => 'plain-hidden', 'is_coming_soon' => false]);

        $this->get('/products/plain-hidden')->assertNotFound();
    }

    public function test_a_coming_soon_product_cannot_be_added_to_the_cart(): void
    {
        $product = $this->comingSoon(['slug' => 'no-buy']);

        // The cart only resolves active products, so this never becomes buyable.
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1])->assertNotFound();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_guest_request_needs_a_phone_and_notifies_staff(): void
    {
        Notification::fake();
        $admin = $this->staff();
        $product = $this->comingSoon(['slug' => 'guest-req']);

        // No phone → validation error, nothing recorded.
        $this->post('/products/guest-req/request', [])->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('product_requests', 0);

        // With a phone → recorded + staff notified.
        $this->post('/products/guest-req/request', ['phone' => '0500000000'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_requests', [
            'product_id' => $product->id,
            'user_id' => null,
            'phone' => '0500000000',
        ]);
        Notification::assertSentTo($admin, ProductRequestedNotification::class);
    }

    public function test_logged_in_request_is_one_click_and_uses_their_phone(): void
    {
        Notification::fake();
        $this->staff();
        $product = $this->comingSoon(['slug' => 'user-req']);
        $customer = User::create(['name' => 'Zaid', 'phone' => '0555555555', 'password' => bcrypt('x')]);

        $this->actingAs($customer)->post('/products/user-req/request', [])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_requests', [
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'phone' => '0555555555',
        ]);
    }

    public function test_duplicate_open_request_is_collapsed(): void
    {
        $product = $this->comingSoon(['slug' => 'dup-req']);
        $customer = User::create(['name' => 'Z', 'phone' => '0511111111', 'password' => bcrypt('x')]);

        $this->actingAs($customer)->post('/products/dup-req/request', []);
        $this->actingAs($customer)->post('/products/dup-req/request', []);

        $this->assertDatabaseCount('product_requests', 1);
    }

    public function test_request_on_a_live_product_404s(): void
    {
        $this->comingSoon(['slug' => 'live-one', 'is_active' => true, 'is_coming_soon' => false, 'price' => 10]);

        $this->post('/products/live-one/request', ['phone' => '0500000000'])->assertNotFound();
        $this->assertDatabaseCount('product_requests', 0);
    }

    public function test_admin_requests_page_lists_and_marks_handled(): void
    {
        $staff = $this->staff();
        $product = $this->comingSoon(['slug' => 'admin-req']);
        $request = ProductRequest::create(['product_id' => $product->id, 'phone' => '0500000000']);

        $this->actingAs($staff)->get('/admin/product-requests')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('admin/product-requests/index')->has('requests.data', 1)->where('openCount', 1),
        );

        $this->actingAs($staff)->post("/admin/product-requests/{$request->id}/handle")->assertRedirect();
        $this->assertNotNull($request->fresh()->handled_at);
    }

    public function test_dashboard_flags_draft_products_to_complete(): void
    {
        $staff = $this->staff();
        $this->comingSoon(['slug' => 'draft-a']);
        $this->comingSoon(['slug' => 'draft-b']);

        $this->actingAs($staff)->get('/admin/dashboard')->assertOk()->assertInertia(
            fn (Assert $page) => $page->where(
                'tasks',
                fn ($tasks) => collect($tasks)->firstWhere('key', 'draftsToComplete')['count'] === 2,
            ),
        );
    }

    public function test_admin_can_toggle_coming_soon_on_a_product(): void
    {
        $staff = $this->staff();
        $product = Product::create([
            'category_id' => $this->category()->id,
            'name_ar' => 'خلاص', 'slug' => 'toggle-cs', 'price' => 10,
            'sku' => 'TG-1', 'stock' => 5, 'is_active' => false, 'is_coming_soon' => false,
        ]);
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/x.jpg', 'is_primary' => true]);

        $this->actingAs($staff)->put("/admin/products/{$product->id}", [
            'category_id' => $product->category_id,
            'name_ar' => 'خلاص', 'price' => 10, 'sku' => 'TG-1', 'stock' => 5,
            'is_active' => false, 'is_coming_soon' => true,
        ])->assertRedirect();

        $this->assertTrue($product->fresh()->is_coming_soon);
    }
}
