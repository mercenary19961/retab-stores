<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The staff front door, separate from the customers' one.
 *
 * 🔑 Why a second page rather than a role check on the shared one: the two
 * audiences need opposite things. Customers get every shortcut we can offer —
 * Google, WhatsApp, and a link to create an account. Staff get none of them:
 * their accounts are created only at /admin/users, they sign in with an email
 * and a password, and a "create an account" link on that page would advertise
 * something that does not exist.
 *
 * ⚠️ Authentication itself is NOT duplicated. Both pages post through
 * LoginRequest, so the credential check, the per-email+IP throttle and the
 * lockout all behave identically — only the destination and what is refused
 * differ. Two copies of a login is how one of them silently loses a rate limit.
 */
class AdminSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/admin-login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // 🔴 Signed in, but not staff. Log them straight back out rather than
        // leaving a customer session created from the staff door — and say so
        // plainly, because "wrong page" and "wrong password" are different
        // problems and a generic failure would send them hunting the latter.
        if (! Auth::user()?->isStaff()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('messages.auth.not_staff'),
            ]);
        }

        $request->session()->regenerate();

        // No cart merge here, deliberately: a member of staff signing in to the
        // back office is not shopping, and merging a guest cart into their
        // account would attach stray items to a staff user.
        return redirect()->intended(route('admin.dashboard', absolute: false));
    }
}
