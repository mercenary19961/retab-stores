<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        // APP_ENV=testing is not "local", so the CSP + HSTS branch is exercised.
        $response->assertHeader('Strict-Transport-Security');
        $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_otp_send_is_blocked_when_turnstile_rejects(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        $response = $this->post('/login/whatsapp/send', [
            'phone' => '+966500000000',
            'cf-turnstile-response' => 'bad-token',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('otp_verifications', 0);
    }

    public function test_otp_send_passes_when_turnstile_unconfigured(): void
    {
        // Turnstile is what is under test here, so the channel has to be deliverable
        // or the OTP guard would reject first and the assertion would pass for the
        // wrong reason.
        $this->withLiveWhatsapp();

        // No secret key (dev default) → verifier no-ops and the code is issued.
        $this->post('/login/whatsapp/send', ['phone' => '+966500000000'])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('otp_verifications', 1);
    }

    public function test_checkout_is_rate_limited(): void
    {
        // The throttle middleware runs before validation — 11th hit within a
        // minute must 429 regardless of payload.
        for ($i = 0; $i < 10; $i++) {
            $this->post('/checkout', []);
        }

        $this->post('/checkout', [])->assertStatus(429);
    }

    public function test_role_and_permissions_are_not_mass_assignable(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        // A plain fill/update must never touch the guarded privilege fields.
        $user->update(['role' => 'admin', 'permissions' => ['orders' => ['view' => true]]]);
        $user->refresh();

        $this->assertSame('customer', $user->role);
        $this->assertNull($user->permissions);
    }

    public function test_profile_update_cannot_escalate_role(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        // Attacker slips role/permissions into the profile form — must be ignored.
        $this->actingAs($user)->patch('/account/profile', [
            'name' => 'Attacker',
            'role' => 'admin',
            'permissions' => ['settings' => ['edit' => true]],
        ])->assertSessionDoesntHaveErrors();

        $user->refresh();
        $this->assertSame('customer', $user->role);
        $this->assertNull($user->permissions);
        $this->assertSame('Attacker', $user->name); // the allowed field still saved
    }

    public function test_cart_quantity_is_capped(): void
    {
        $category = Category::create(['slug' => 'c', 'name_ar' => 'ت', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name_ar' => 'تمر', 'slug' => 'p',
            'sku' => 'SKU1', 'price' => 10, 'stock' => 500, 'is_active' => true,
        ]);

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1000])
            ->assertSessionHasErrors('quantity');
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/forgot-password', ['email' => 'x@example.com']);
        }

        $this->post('/forgot-password', ['email' => 'x@example.com'])->assertStatus(429);
    }

    public function test_login_is_ip_rate_limited(): void
    {
        // Different emails each hit so the per-email limiter (5) never fires first;
        // the per-IP route throttle (20/min) must 429 the 21st attempt.
        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', ['email' => "u{$i}@example.com", 'password' => 'x']);
        }

        $this->post('/login', ['email' => 'final@example.com', 'password' => 'x'])->assertStatus(429);
    }

    /**
     * 🔴 Regression guard for a live revenue bug (fixed 2026-08-06).
     *
     * Laravel's unnamed `throttle:max,decay` keys its counter on
     * `sha1(domain|IP)` — the route URI is NOT in the key. So every rate-limited
     * route shared ONE bucket per visitor while each compared that shared count
     * against its own limit, and the strictest route rejected once the visitor's
     * COMBINED requests passed it. Concretely: a shopper who added 11 items to
     * their cart (limit 60 — allowed) then clicked checkout (limit 10) got a 429
     * and could not pay.
     *
     * The fix is the prefix argument (`throttle:60,1,cart`) giving each group its
     * own counter. This test spends far more than checkout's limit on the CART
     * route and then asserts checkout is still reachable.
     */
    public function test_cart_requests_do_not_consume_the_checkout_rate_limit(): void
    {
        $product = $this->throttleProbeProduct();

        // 15 cart writes — comfortably inside cart's own 60/min, but well past
        // checkout's 10/min if the two ever share a bucket again.
        for ($i = 0; $i < 15; $i++) {
            $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        }

        // Checkout must NOT be throttled by the cart activity above. Asserting
        // "not 429" rather than a specific status is the actual claim — the
        // request may legitimately redirect back with validation errors.
        $this->assertNotSame(
            429,
            $this->post('/checkout', [])->status(),
            'cart activity must not consume checkout’s rate limit',
        );
    }

    /** Same isolation, on the auth side: browsing must not lock a shopper out of signing in. */
    public function test_cart_requests_do_not_consume_the_otp_rate_limit(): void
    {
        $product = $this->throttleProbeProduct();

        for ($i = 0; $i < 10; $i++) {
            $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        }

        // OTP send allows 6/min on its OWN counter; the cart hits above must not
        // have spent it, so this first request cannot be a 429.
        $this->assertNotSame(
            429,
            $this->post('/login/whatsapp/send', ['phone' => '0512345678'])->status(),
            'cart activity must not consume the OTP rate limit',
        );
    }

    private function throttleProbeProduct(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'throttle-probe'], ['name_ar' => 'ت', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id, 'name_ar' => 'تمر', 'slug' => 'throttle-probe-'.uniqid(),
            'sku' => 'TP-'.uniqid(), 'price' => 10, 'stock' => 500, 'is_active' => true,
        ]);
    }
}
