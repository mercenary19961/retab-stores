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
}
