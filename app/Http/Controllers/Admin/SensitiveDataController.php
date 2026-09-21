<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SensitiveDataDeletionMail;
use App\Models\Setting;
use App\Support\SensitiveSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Revealing and deleting the business's financial identifiers.
 *
 * 🔴 The threat this is built against is a STOLEN ADMIN SESSION, not a curious
 * colleague. Anyone holding one can already change the IBAN, and every customer
 * paying by bank transfer would then pay an attacker while the receipt email,
 * the order page and the admin panel all agreed with each other. Masking is only
 * shoulder-surfing cover; **the emailed confirmation is the actual control**,
 * because it demands something a session alone cannot provide.
 */
class SensitiveDataController extends Controller
{
    /** How long the emailed link stays good. Short: it is a destructive action. */
    private const TOKEN_TTL_MINUTES = 30;

    private static function cacheKey(string $token): string
    {
        return 'sensitive-delete:'.$token;
    }

    /**
     * Hand back one real value so it can be edited.
     *
     * Gated on `settings.edit` rather than ownership: an admin who may CHANGE the
     * IBAN must obviously be able to see the one they are changing. Ownership
     * guards deletion, which is the irreversible half.
     */
    public function reveal(Request $request): JsonResponse
    {
        $key = (string) $request->query('key');

        abort_unless(SensitiveSettings::isSensitive($key), 404);

        // Worth a log line: reading these is rare and deliberate, so a burst of
        // reveals is exactly the signal an incident review would want.
        Log::info('Sensitive setting revealed', ['key' => $key, 'user_id' => Auth::id()]);

        return response()->json(['value' => (string) Setting::get($key)]);
    }

    /**
     * Step 1 of 2. The owner asks; the mailbox confirms.
     *
     * 🔴 Nothing is deleted here. This only mints a single-use token and posts it
     * to the owner's address, which is the whole point: possession of the session
     * is not enough, possession of the inbox is also required.
     */
    public function requestDeletion(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Belt and braces with the route middleware. The owner rule lives on the
        // model so it cannot drift between the two.
        abort_unless((bool) $user?->isOwner(), 403);

        if (! SensitiveSettings::anyPresent()) {
            return back()->with('error', __('messages.admin.sensitive_nothing_to_delete'));
        }

        /*
         * 🔴 Refuse while bank transfer is still on offer. Deleting the IBAN
         * underneath an enabled payment method leaves every new bank-transfer
         * order with nowhere to pay and no error anywhere — the failure would
         * surface days later as customers asking where to send money.
         *
         * Refusing rather than auto-disabling, which is this codebase's settled
         * shape for this class of guard (the last-payment-method and last-carrier
         * guards both refuse up front rather than cascade).
         */
        if (Setting::get('payment_bank_transfer_enabled', '1') === '1') {
            return back()->with('error', __('messages.admin.sensitive_bank_transfer_on'));
        }

        $token = Str::random(64);
        Cache::put(self::cacheKey($token), $user->id, now()->addMinutes(self::TOKEN_TTL_MINUTES));

        $url = URL::temporarySignedRoute(
            'admin.sensitive.confirm',
            now()->addMinutes(self::TOKEN_TTL_MINUTES),
            ['token' => $token],
        );

        // Sent, deliberately, to the OWNER ADDRESS ON FILE rather than to
        // `$user->email`. They are the same account today; hardcoding the
        // relationship means a future change to who the owner is cannot quietly
        // redirect the confirmation somewhere else.
        /*
         * ⚠️ The mailable is BUILT OUTSIDE the try on purpose. A broad catch around
         * construction turns a programming error into a friendly "could not send"
         * flash — which is exactly what happened while writing this: an invalid
         * trait-constant reference threw, and the catch reported a mail outage.
         * Only the TRANSPORT is allowed to fail softly.
         */
        $mail = new SensitiveDataDeletionMail($url, self::TOKEN_TTL_MINUTES);

        try {
            Mail::to((string) config('retab.owner_email'))->send($mail);
        } catch (\Throwable $e) {
            // ⚠️ A provider outage must not look like success. Drop the token so
            // a link that was never delivered cannot be used, and say so plainly
            // rather than leaving the owner watching an inbox for nothing.
            Cache::forget(self::cacheKey($token));
            Log::error('Sensitive deletion email failed', ['error' => $e->getMessage()]);

            return back()->with('error', __('messages.admin.sensitive_mail_failed'));
        }

        Log::warning('Sensitive data deletion requested', ['user_id' => $user->id]);

        return back()->with('success', __('messages.admin.sensitive_delete_sent'));
    }

    /**
     * Step 2 of 2, reached from the emailed link.
     *
     * ⚠️ Requires the signed URL AND a signed-in owner. The link alone would make
     * a forwarded email sufficient; the session alone is what we are defending
     * against. Both together is the point.
     */
    public function confirmDeletion(Request $request, string $token): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless((bool) $request->user()?->isOwner(), 403);

        $key = self::cacheKey($token);

        // 🔑 Consuming the token is what makes the link single use. A signed URL
        // on its own stays replayable for its whole lifetime, so someone with the
        // email could re-run the deletion after the values were re-entered.
        if (Cache::pull($key) === null) {
            return redirect()
                ->route('admin.settings.edit')
                ->with('error', __('messages.admin.sensitive_link_expired'));
        }

        DB::transaction(fn () => SensitiveSettings::purge());

        Log::warning('Sensitive data deleted', ['user_id' => $request->user()->id]);

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', __('messages.admin.sensitive_deleted'));
    }
}
