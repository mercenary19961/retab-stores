<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The production error-response shaping wired up in bootstrap/app.php.
 *
 * It is a no-op in `testing` (the rest of the suite keeps asserting raw status
 * codes), and in `local` it shapes CLIENT errors only, leaving server errors on
 * the framework's trace page. So each test forces the branch it means to
 * exercise with `$this->app['env'] = ...` — the same technique SeoTest uses to
 * exercise production-only URL pinning.
 */
class ErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    private function asProduction(): void
    {
        $this->app['env'] = 'production';

        // 🔴 Overriding `env` also switches CSRF verification back ON, because
        // ValidateCsrfToken opts out via $app->runningUnitTests(), which is
        // literally `env === 'testing'`. Without this every POST/DELETE here 419s
        // and the assertions pass on the page-expired branch instead of the one
        // actually under test — which is exactly how the inline-403 test below
        // used to pass.
        $this->withoutMiddleware(ValidateCsrfToken::class);
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
        // ⚠️ Assert the MESSAGE, not merely that some error was flashed: a 419
        // also redirects back with an error, so a bare assertSessionHas() passes
        // whether or not the branch under test ever ran.
        $this->assertContains($response->getStatusCode(), [302, 303]);
        $response->assertSessionHas('error', __('messages.admin.no_permission'));

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

    /**
     * A rate-limited page visit gets the branded page rather than the framework's
     * bare 429, and carries the seconds until the limit clears so the page can
     * count down instead of vaguely saying "later".
     */
    public function test_a_rate_limited_visit_renders_the_branded_page_with_a_countdown(): void
    {
        $this->asProduction();

        Route::middleware(['web', 'throttle:1,1,error-test-visit'])
            ->get('/__throttled-visit', fn () => 'ok');

        $this->get('/__throttled-visit')->assertOk();

        $response = $this->get('/__throttled-visit');

        $response->assertStatus(429)
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/error')
                ->where('status', 429)
                ->where('retryAfter', fn ($seconds) => is_int($seconds) && $seconds > 0));

        // Inertia builds a FRESH response, so the header a client (or a crawler
        // backing off) acts on is lost unless it is carried across by hand.
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    /**
     * An admin form submitted faster than its rate limit allows keeps the admin on
     * their page with a flash the toast layer renders, rather than swapping the
     * whole screen — and whatever they had typed — for the error page.
     */
    public function test_a_rate_limited_admin_inline_action_redirects_back_with_a_flash(): void
    {
        $this->asProduction();

        Route::middleware(['web', 'throttle:1,1,error-test-admin'])
            ->post('/admin/__throttled-action', fn () => back());

        $this->withHeader('X-Inertia', 'true')->from('/admin/products')->post('/admin/__throttled-action');

        $response = $this->withHeader('X-Inertia', 'true')
            ->from('/admin/products')
            ->post('/admin/__throttled-action');

        $this->assertContains($response->getStatusCode(), [302, 303]);
        $response->assertSessionHas('error', __('messages.errors.too_many_requests'));
    }

    /**
     * The mirror of the case above, and the whole reason the flash-back is scoped
     * to the admin: the STOREFRONT mounts no flash renderer, so a shopper bounced
     * back would watch the button silently do nothing. The branded page at least
     * says what happened and when they can retry.
     */
    public function test_a_rate_limited_storefront_inline_action_gets_the_branded_page(): void
    {
        $this->asProduction();

        Route::middleware(['web', 'throttle:1,1,error-test-store'])
            ->post('/__throttled-store-action', fn () => back());

        $this->withHeader('X-Inertia', 'true')->post('/__throttled-store-action');

        // ⚠️ Asserted as JSON rather than with assertInertia(), which only
        // understands a full-page response: to an X-Inertia request Inertia
        // answers with the page object as JSON, not a rendered Blade view.
        $this->withHeader('X-Inertia', 'true')
            ->post('/__throttled-store-action')
            ->assertStatus(429)
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'errors/error')
            ->assertJsonPath('props.status', 429);
    }

    /**
     * The branded page must speak the visitor's language, and it cannot lean on
     * the shared `locale` prop to do it: on the 404 path the router throws before
     * ANY web middleware runs, so Inertia has shared nothing at all and
     * SetLocale never set the app locale either. The long-lived `locale` cookie
     * is the only source still available that far up the stack.
     */
    public function test_the_branded_page_renders_in_the_visitors_language(): void
    {
        $this->asProduction();

        // ⚠️ withUnencryptedCookie, not withCookie: the latter ENCRYPTS the value
        // for EncryptCookies to unwrap, but `locale` is in this app's encryption
        // except-list and is genuinely plaintext — and on the 404 path
        // EncryptCookies never runs anyway, so an encrypted blob reads as garbage
        // and silently falls back to Arabic.
        $this->withUnencryptedCookie('locale', 'en')
            ->get('/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page) => $page->component('errors/error')->where('locale', 'en'));

        $this->withUnencryptedCookie('locale', 'ar')
            ->get('/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page) => $page->component('errors/error')->where('locale', 'ar'));
    }

    /** A visitor who has never chosen a language gets the Arabic default, not a missing prop. */
    public function test_the_branded_page_falls_back_to_arabic(): void
    {
        $this->asProduction();

        $this->get('/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page) => $page->component('errors/error')->where('locale', 'ar'));
    }

    /**
     * Local renders CLIENT errors branded — otherwise the storefront's commonest
     * error states could only ever be seen in production — while SERVER errors
     * keep the framework's trace page, which is the whole point of local.
     */
    public function test_local_brands_client_errors_but_keeps_the_trace_for_server_errors(): void
    {
        $this->app['env'] = 'local';

        // Debug OFF so the server-error branch renders the framework's own small
        // page. With Ignition on, its trace embeds the SOURCE of bootstrap/app.php
        // — which literally contains the string 'errors/error' — so a content
        // assertion would match the handler's own code rather than a rendered page.
        config(['app.debug' => false]);

        $this->get('/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page) => $page->component('errors/error')->where('status', 404));

        Route::middleware(['web'])->get('/__boom', function () {
            throw new \RuntimeException('boom');
        });

        $response = $this->get('/__boom');

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('errors/error', $response->getContent());
    }
}
