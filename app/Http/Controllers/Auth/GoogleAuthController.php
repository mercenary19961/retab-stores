<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * Sign in with Google.
 *
 * 🔑 Identity is the `social_accounts` row (provider + provider_id), NOT the email
 * address. Google's subject id never changes; an email can. Matching on email
 * alone — which the hardrock implementation this was modelled on does — hands a
 * customer a brand-new account the day they change their Gmail address, losing
 * their orders, loyalty count and saved details.
 *
 * ⚠️ Both routes 404 unless Google is configured, so the app never redirects a
 * customer to an OAuth screen that cannot complete. `isConfigured()` is also what
 * gates the button, so a dead door is never rendered in the first place.
 */
class GoogleAuthController extends Controller
{
    /** Is a Google OAuth client actually set up? */
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function redirect(): RedirectResponse
    {
        abort_unless(self::isConfigured(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(self::isConfigured(), 404);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            // The OAuth state cookie expired or the tab was left open too long.
            // Recoverable and entirely the customer's-browser's doing, so it is a
            // warning and a "try again", not an error.
            Log::warning('Google OAuth invalid state', ['error' => $e->getMessage()]);

            return redirect()->route('login')->with('error', __('messages.auth.google_expired'));
        } catch (\Throwable $e) {
            Log::error('Google OAuth failed', ['error' => $e->getMessage()]);

            return redirect()->route('login')->with('error', __('messages.auth.google_failed'));
        }

        if (blank($googleUser->getId())) {
            return redirect()->route('login')->with('error', __('messages.auth.google_failed'));
        }

        Auth::login($this->resolveUser($googleUser), remember: true);

        // Honours the page they were sent here from — which is what makes this
        // usable from checkout as well as from the login page.
        return redirect()->intended(route('account.dashboard', absolute: false));
    }

    /**
     * Find, link, or create the account behind this Google identity.
     *
     * Three cases, in order of how much we trust them:
     *   1. We have seen this Google id before  → that account, definitively.
     *   2. An account already has this email   → link it, but only if Google says
     *                                            the address is verified.
     *   3. Neither                             → create a customer.
     */
    private function resolveUser(SocialiteUser $googleUser): User
    {
        return DB::transaction(function () use ($googleUser) {
            $providerId = (string) $googleUser->getId();
            $email = $googleUser->getEmail();

            $link = SocialAccount::where('provider', 'google')
                ->where('provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            if ($link && $link->user) {
                // Avatars change; refresh it so the account does not keep a stale one.
                $link->update(['avatar' => $googleUser->getAvatar()]);
                $this->fillBlanks($link->user, $googleUser);

                return $link->user;
            }

            $user = $this->matchableByEmail($googleUser)
                ? User::where('email', $email)->lockForUpdate()->first()
                : null;

            if (! $user) {
                // 🔴 forceCreate, not create: `role` is deliberately excluded from
                // User::$fillable as a privilege field, so mass assignment would
                // silently drop it and leave the account role-less.
                $user = User::forceCreate([
                    'name' => $googleUser->getName(),
                    'email' => $email,
                    'avatar' => $googleUser->getAvatar(),
                    'role' => 'customer',
                    // Google has already proven the address, so re-verifying it by
                    // email would ask the customer to confirm what we just confirmed.
                    'email_verified_at' => $email ? now() : null,
                    // No password: this account signs in through Google. `users`
                    // allows a null password under the OTP identity model, and the
                    // customer can set one later from their profile.
                    'password' => null,
                ]);
            } else {
                $this->fillBlanks($user, $googleUser);
            }

            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => 'google',
                'provider_id' => $providerId,
                'avatar' => $googleUser->getAvatar(),
            ]);

            return $user;
        });
    }

    /**
     * May we attach this Google identity to an existing account with the same
     * email?
     *
     * 🔴 Only when Google states the address is verified. Without that check,
     * anyone who can get Google to issue them a token carrying somebody else's
     * unverified address takes over that account — a full account-takeover for
     * the price of a sign-up. Google Workspace domains can mint such addresses.
     *
     * The claim is not on Socialite's typed interface, so it is read from the raw
     * payload and treated as false when absent.
     */
    private function matchableByEmail(SocialiteUser $googleUser): bool
    {
        if (blank($googleUser->getEmail())) {
            return false;
        }

        $raw = $googleUser->getRaw();

        return ($raw['email_verified'] ?? false) === true;
    }

    /**
     * Fill only what the account is missing. Never overwrites: a customer who set
     * their own name here should not have it replaced by their Google one on the
     * next sign-in.
     */
    private function fillBlanks(User $user, SocialiteUser $googleUser): void
    {
        $fill = array_filter([
            'name' => blank($user->name) ? $googleUser->getName() : null,
            'avatar' => blank($user->avatar) ? $googleUser->getAvatar() : null,
        ]);

        if ($fill !== []) {
            $user->forceFill($fill)->save();
        }
    }
}
