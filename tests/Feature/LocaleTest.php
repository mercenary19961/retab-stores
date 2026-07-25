<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_locale_toggle_is_stored_in_the_session(): void
    {
        $this->post('/locale/en')
            ->assertOk()
            ->assertJson(['ok' => true, 'locale' => 'en']);

        $this->assertSame('en', session('locale'));
    }

    public function test_authenticated_toggle_persists_to_the_user_account(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);

        $this->actingAs($user)->post('/locale/en')->assertOk();

        // Follows the account, not just the session.
        $this->assertSame('en', $user->fresh()->locale);
        $this->assertSame('en', session('locale'));
    }

    public function test_saved_user_locale_is_used_when_the_session_has_none(): void
    {
        // Fresh login on a new device: no session locale yet, so the middleware
        // falls back to the user's saved preference and the shared prop reflects it.
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_guest_toggle_sets_a_long_lived_cookie(): void
    {
        // The `locale` cookie is plaintext (excluded from encryption), so assert
        // it without decryption (3rd arg = false).
        $this->post('/locale/en')
            ->assertOk()
            ->assertCookie('locale', 'en', false);
    }

    public function test_returning_guest_uses_the_locale_cookie(): void
    {
        // No session, no account — a returning guest whose session expired but
        // whose 1-year cookie survived still gets their chosen language. Use the
        // unencrypted helper since the cookie is excluded from encryption.
        $this->withUnencryptedCookie('locale', 'en')->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }
}
