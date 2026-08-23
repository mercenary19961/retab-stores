<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\MailAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Staff accounts are deliberately created on a non-routable domain so
        // they have no self-service way back in (see App\Support\MailAddress).
        // Refuse before the broker rather than after: a reset mail queued to a
        // dead domain is a guaranteed bounce, and bounces cost sending
        // reputation on an account this store depends on for real receipts.
        //
        // 🔑 The check is on the SUBMITTED address, not on a looked-up account,
        // so it is enumeration-safe — the answer is identical whether or not a
        // user exists, and it only restates what the submitter already typed.
        if (! MailAddress::isDeliverable($request->string('email')->value())) {
            throw ValidationException::withMessages([
                'email' => __('messages.security.reset_not_available'),
            ]);
        }

        Password::sendResetLink(
            $request->only('email')
        );

        return back()->with('status', __('A reset link will be sent if the account exists.'));
    }
}
