<?php

namespace Tests\Feature\Security;

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
}
