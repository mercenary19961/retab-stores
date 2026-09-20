<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Sign in with Google.
 *
 * 🔴 This class exists because the controller shipped with NO tests at all, and
 * the very first real sign-in on production 500'd on an avatar URL too long for
 * its column. Nothing had ever exercised the insert.
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Both routes 404 without a configured client, so every test needs one.
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);
    }

    /**
     * Stand Socialite up so the callback receives this identity.
     *
     * @param  array<string,mixed>  $attributes
     */
    private function googleReturns(array $attributes = [], bool $emailVerified = true): SocialiteUser
    {
        $user = (new SocialiteUser)
            ->setRaw(['email_verified' => $emailVerified])
            ->map([
                'id' => '110000000000000000001',
                'name' => 'Zaid Sabbagh',
                'email' => 'zaid@example.com',
                'avatar' => 'https://lh3.googleusercontent.com/a-/short',
                ...$attributes,
            ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        return $user;
    }

    public function test_a_first_time_sign_in_creates_a_customer_account(): void
    {
        $this->googleReturns();

        $this->get(route('auth.google.callback'))->assertRedirect('/account');

        $user = User::firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertSame('zaid@example.com', $user->email);
        // Google proved the address, so we do not ask the customer to prove it again.
        $this->assertNotNull($user->email_verified_at);
        // Google is the credential; there is no password to set.
        $this->assertNull($user->password);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google']);
    }

    /**
     * 🔴 THE REGRESSION. Google issues avatar URLs well past the VARCHAR(255)
     * this column used to be, which threw SQLSTATE[22001] inside resolveUser()'s
     * transaction — so the account was rolled back and the customer got a 500
     * rather than an account.
     *
     * ⚠️ Note what this test can and cannot prove: SQLite does NOT enforce VARCHAR
     * length, so on the test connection the raw insert would have succeeded even
     * before the fix. What it pins is the GUARD. The column width is pinned
     * separately below, and the two together are what make production safe.
     */
    public function test_an_avatar_longer_than_the_column_does_not_break_sign_in(): void
    {
        $this->googleReturns([
            'avatar' => 'https://lh3.googleusercontent.com/a-/'.str_repeat('A', 2000),
        ]);

        $this->get(route('auth.google.callback'))->assertRedirect('/account');

        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        // Dropped, never truncated: a cut-off URL is a broken image, which is
        // worse than no picture at all.
        $this->assertNull($user->avatar);
        $this->assertNull(SocialAccount::firstOrFail()->avatar);
    }

    /**
     * Pins the migration to GoogleAuthController::AVATAR_MAX. Widening one without
     * the other silently reintroduces the production crash, because the guard
     * would wave through a URL the column cannot hold.
     *
     * ⚠️ This reads the MIGRATION SOURCE rather than the live schema, and it has
     * to. Laravel's SQLite grammar renders every string column as a bare `varchar`
     * with the length DISCARDED (SQLiteGrammar::typeString returns 'varchar', full
     * stop), so on the test connection the width does not exist to be read back.
     * Asserting it against MySQL only would mean this never runs in CI, which is
     * the one place it has to.
     */
    public function test_the_migration_declares_avatar_columns_wide_enough_for_the_guard(): void
    {
        $migration = (string) file_get_contents(
            database_path('migrations/2026_09_20_300000_widen_avatar_urls.php'),
        );

        // Only up() states a length; down() restores the unqualified default.
        preg_match_all("/string\('avatar', (\d+)\)/", $migration, $matches);

        $this->assertCount(2, $matches[1], 'Expected both avatar columns to be widened');

        foreach ($matches[1] as $length) {
            $this->assertGreaterThanOrEqual(
                GoogleAuthController::AVATAR_MAX,
                (int) $length,
                'An avatar column is narrower than GoogleAuthController::AVATAR_MAX',
            );
        }
    }

    /** An avatar that fits is stored, so the guard is not simply dropping everything. */
    public function test_an_avatar_that_fits_is_kept(): void
    {
        $this->googleReturns(['avatar' => 'https://lh3.googleusercontent.com/a-/fits']);

        $this->get(route('auth.google.callback'));

        $this->assertSame('https://lh3.googleusercontent.com/a-/fits', User::firstOrFail()->avatar);
    }

    /**
     * Identity is the Google subject id, not the email — so a customer who changes
     * their Gmail address keeps their orders, loyalty count and saved addresses.
     */
    public function test_a_returning_customer_signs_into_the_same_account(): void
    {
        $existing = User::forceCreate(['name' => 'Zaid', 'email' => 'old@example.com', 'role' => 'customer']);
        SocialAccount::create([
            'user_id' => $existing->id,
            'provider' => 'google',
            'provider_id' => '110000000000000000001',
        ]);

        $this->googleReturns(['email' => 'brand-new@example.com']);

        $this->get(route('auth.google.callback'))->assertRedirect('/account');

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame(1, User::count());
        $this->assertSame(1, SocialAccount::count());
    }

    public function test_a_verified_google_email_links_to_an_existing_account(): void
    {
        $existing = User::forceCreate([
            'name' => 'Zaid', 'email' => 'zaid@example.com', 'password' => bcrypt('x'), 'role' => 'customer',
        ]);

        $this->googleReturns(emailVerified: true);

        $this->get(route('auth.google.callback'))->assertRedirect('/account');

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame(1, User::count());
    }

    /**
     * 🔴 The security control. Without the email_verified check, anyone able to
     * get a token carrying somebody else's unverified address takes over that
     * account. A separate account is the correct, safe outcome.
     */
    public function test_an_unverified_google_email_does_not_link_to_an_existing_account(): void
    {
        $existing = User::forceCreate([
            'name' => 'Zaid', 'email' => 'zaid@example.com', 'password' => bcrypt('x'), 'role' => 'admin',
        ]);

        $this->googleReturns(emailVerified: false);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        // Nothing was linked, nothing was created, and the admin account is
        // untouched — an unverified address must never reach it.
        $this->assertGuest();
        $this->assertSame(1, User::count());
        $this->assertSame('admin', $existing->fresh()->role);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    /** A door that cannot open is never offered. */
    public function test_the_routes_404_when_no_client_is_configured(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get(route('auth.google'))->assertNotFound();
        $this->get(route('auth.google.callback'))->assertNotFound();
    }
}
