<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The production error-response shaping wired up in bootstrap/app.php.
 *
 * It is deliberately a no-op in local + testing (dev keeps the trace page, the
 * rest of the suite keeps asserting raw status codes), so every test here forces
 * the production branch with `$this->app['env'] = 'production'` — the same
 * technique SeoTest uses to exercise production-only URL pinning.
 */
class ErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    private function asProduction(): void
    {
        $this->app['env'] = 'production';
    }

    private function product(): Product
    {
        $category = Category::create(['name_ar' => 'تمور', 'slug' => 'c-'.uniqid()]);

        return Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'slug' => 'p-'.uniqid(),
            'price' => 50,
            'sku' => 'SK-'.uniqid(),
            'smacc_sku' => 'SM-'.uniqid(),
            'stock' => 10,
        ]);
    }

    /** In the test environment the raw status is preserved, so the rest of the suite is unaffected. */
    public function test_testing_environment_keeps_the_raw_forbidden_status(): void
    {
        $editor = User::factory()->create(['role' => 'editor']); // settings.view denied by default

        // No env override → the handler returns the framework response untouched.
        $this->actingAs($editor)->get('/admin/settings')->assertForbidden();
    }

    public function test_a_forbidden_full_page_visit_renders_the_branded_error_page(): void
    {
        $this->asProduction();
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)
            ->get('/admin/settings')
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page->component('errors/error')->where('status', 403));
    }

    public function test_a_missing_page_renders_the_branded_error_page(): void
    {
        $this->asProduction();

        $this->get('/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page) => $page->component('errors/error')->where('status', 404));
    }

    /**
     * A refused INLINE write — the stale button case — must keep the user on
     * their page with a flash the admin toast layer renders, never swap the whole
     * screen to the error page. Detected by the Inertia header + a non-GET method.
     */
    public function test_a_refused_inline_action_redirects_back_with_a_flash(): void
    {
        $this->asProduction();
        $editor = User::factory()->create(['role' => 'editor']); // products.delete denied by default
        $product = $this->product();

        $response = $this->actingAs($editor)
            ->withHeader('X-Inertia', 'true')
            ->from('/admin/products')
            ->delete("/admin/products/{$product->id}");

        // Not the branded page: a redirect back, carrying the error flash.
        $this->assertContains($response->getStatusCode(), [302, 303]);
        $response->assertSessionHas('error');

        // And the action genuinely did nothing — the middleware still blocked it.
        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    /**
     * A JSON/API client keeps the machine-readable response rather than an HTML
     * page it cannot use.
     */
    public function test_a_json_client_keeps_the_raw_status(): void
    {
        $this->asProduction();
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)
            ->getJson('/admin/settings')
            ->assertStatus(403);
    }
}
