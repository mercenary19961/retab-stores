<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceivedNotification;
use App\Services\TurnstileVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * The Contact Us page's "أرسل رسالتك" form (contact-submit.tsx). Every submitter —
 * guest or signed-in — fills the same fields; see the migration for why no
 * user_id is recorded.
 */
class ContactMessageController extends Controller
{
    /**
     * Keys must match `contact.form.inquiryTypes` in i18n — that array is what
     * renders the `<select>`, so the two are kept in step deliberately rather than
     * generated from one shared source (there is no server-driven copy on this
     * page otherwise, unlike e.g. return reasons).
     */
    private const INQUIRY_TYPES = ['order', 'product', 'complaint', 'partnership', 'other'];

    public function store(Request $request, TurnstileVerifier $turnstile): RedirectResponse
    {
        // Bot gate for guests only, same convention as the Coming-Soon "I want
        // this" form: a signed-in visitor already carries an accountable session,
        // an anonymous one does not.
        //
        // ⚠️ Keyed 'turnstile', NOT a real field name. ProductRequestController
        // reuses 'phone' for this because phone is its only field — copying that
        // here onto 'message' would attach a bot-check failure to the message
        // textarea's own error slot, so a perfectly valid message would show a
        // "couldn't verify you're not a robot" error under it. The frontend
        // renders `errors.turnstile` as its own form-level banner instead.
        if (! $request->user() && ! $turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return back()->withErrors(['turnstile' => __('messages.security.verify_failed')]);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'inquiry_type' => ['required', 'string', 'in:'.implode(',', self::INQUIRY_TYPES)],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $contactMessage = ContactMessage::create([...$data, 'ip' => $request->ip()]);

        Notification::send(User::staff()->get(), new ContactMessageReceivedNotification($contactMessage));

        return back(303)->with('success', __('messages.contact.received'));
    }
}
