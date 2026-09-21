<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\ClientReview;
use App\Models\ContentPage;
use App\Models\Setting;
use App\Services\ChangeLog\ChangeLogService;
use App\Services\CheckoutService;
use App\Support\SensitiveSettings;
use Database\Seeders\ClientReviewSeeder;
use Database\Seeders\ContentPageSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Store-wide settings the client controls without a deploy: the flat GCC
 * shipping fee (single number by decision) and the bank-transfer details shown
 * on the order page. All writes go through Setting::set (single write path).
 */
class SettingController extends Controller
{
    /** key => validation rule. The editable allowlist — never accept arbitrary keys. */
    private const FIELDS = [
        CheckoutService::SHIPPING_FEE_KEY => ['required', 'numeric', 'min:0'],
        'legal_name' => ['nullable', 'string', 'max:255'],
        'bank_name' => ['nullable', 'string', 'max:255'],
        'bank_beneficiary' => ['nullable', 'string', 'max:255'],
        'bank_account' => ['nullable', 'string', 'max:64'],
        'bank_iban' => ['nullable', 'string', 'max:34'],
        // Footer / contact block (shown site-wide via HandleInertiaRequests).
        'contact_phone' => ['nullable', 'string', 'max:32'],
        'contact_email' => ['nullable', 'email', 'max:255'],
        'commercial_registration' => ['nullable', 'string', 'max:32'],
        'vat_number' => ['nullable', 'string', 'max:32'],
        'social_snapchat' => ['nullable', 'url', 'max:255'],
        'social_facebook' => ['nullable', 'url', 'max:255'],
        'social_instagram' => ['nullable', 'url', 'max:255'],
        'social_x' => ['nullable', 'url', 'max:255'],
        'social_linkedin' => ['nullable', 'url', 'max:255'],
        // Admin UX: the "How it works" attention beam (stored '1'/'0').
        'admin_help_pulse' => ['boolean'],
        // Which payment methods checkout offers (stored '1'/'0'). Keys come from
        // the enum so a new method cannot be added without a home here.
        'payment_card_enabled' => ['boolean'],
        'payment_tamara_enabled' => ['boolean'],
        'payment_bank_transfer_enabled' => ['boolean'],
    ];

    /** Settings stored as '1'/'0' rather than free text. */
    private const BOOLEAN_FIELDS = [
        'admin_help_pulse',
        'payment_card_enabled',
        'payment_tamara_enabled',
        'payment_bank_transfer_enabled',
    ];

    /**
     * Footer/contact defaults: the storefront fallback when a key is unset, and
     * the admin form placeholders. Single source of truth, also read by
     * HandleInertiaRequests to build the shared `footer` prop.
     */
    public const FOOTER_DEFAULTS = [
        'contact_phone' => '+966 5 5088 3845',
        'contact_email' => 'Info@retab.com.sa',
        'commercial_registration' => '7001744098',
        'vat_number' => '300789485500003',
        'social_snapchat' => 'https://www.snapchat.com/add/retab_dates',
        'social_facebook' => 'https://www.facebook.com/retab_dates',
        'social_instagram' => 'https://www.instagram.com/retab_dates',
        'social_x' => 'https://x.com/retab_dates',
        'social_linkedin' => 'https://www.linkedin.com/company/retab_dates',
    ];

    public function edit()
    {
        return Inertia::render('admin/settings/index', [
            // 🔴 Masked, not raw. Shipping the real IBAN in the page payload and
            // hiding it with CSS would be masking theatre — the value would still
            // sit in the DOM and in every screenshot of the page source. The real
            // value is fetched only when someone explicitly reveals it.
            'settings' => SensitiveSettings::maskAll(
                collect(array_keys(self::FIELDS))
                    ->mapWithKeys(fn (string $key) => [$key => Setting::get($key)])
                    ->all(),
            ),
            'sensitiveKeys' => SensitiveSettings::KEYS,
            // Deleting them is the owner's alone, and only with the emailed
            // confirmation. See Admin\SensitiveDataController.
            'canDeleteSensitive' => (bool) Auth::user()?->isOwner(),
            'hasSensitive' => SensitiveSettings::anyPresent(),
            'defaults' => self::FOOTER_DEFAULTS, // shown as placeholders / effective fallback
            'undoMeta' => session('undo:settings'),
            'canReset' => (bool) Auth::user()?->isAdmin(), // handover-reset is admin-only
        ]);
    }

    /**
     * Admin-only safeguard: restore the editable CONTENT to its project-handover
     * state — store settings, the three content pages, and the homepage review
     * pool. Deliberately leaves all business data (orders, customers, products,
     * inventory, returns, payments) untouched. Not reversible.
     */
    public function reset(): RedirectResponse
    {
        abort_unless((bool) Auth::user()?->isAdmin(), 403);

        DB::transaction(function () {
            // 1) Store settings → handover values (overwrites edits).
            foreach (SettingsSeeder::defaults() as $key => $value) {
                Setting::set($key, $value);
            }

            // 2) Content pages → handover text (force overwrite; the seeder itself
            //    is intentionally non-destructive, so restore explicitly here).
            foreach (ContentPageSeeder::pages() as $page) {
                ContentPage::updateOrCreate(['slug' => $page['slug']], [...$page, 'is_published' => true]);
            }

            // 3) Homepage reviews → exactly the handover pool (discard curated ones).
            ClientReview::query()->delete();
            foreach (ClientReviewSeeder::reviews() as $i => $r) {
                ClientReview::create($r + ['source' => 'manual', 'is_active' => true, 'sort_order' => $i]);
            }
        });

        Log::warning('Site content reset to handover defaults', ['user_id' => Auth::id()]);

        return back()->with('success', __('messages.admin.content_reset'));
    }

    /**
     * Quick edit of the flat shipping fee from the admin top bar.
     *
     * 🔑 It exists because this is the ONE setting the client changes often and
     * the only one that changes what every customer pays — burying it three
     * clicks deep in a form of twenty fields made a routine change feel risky.
     *
     * 🔴 It goes through the SAME Setting::set + change log as the full form. A
     * fee changed from the navbar that did not appear in the audit trail would
     * be the worst possible omission here: this number decides the price of
     * every order, and "who changed shipping to 50?" has to be answerable.
     */
    public function updateShippingFee(Request $request, ChangeLogService $changeLog)
    {
        $key = CheckoutService::SHIPPING_FEE_KEY;

        // Reuses the main form's rule rather than restating it, so the two can
        // never disagree about what a valid fee is.
        $data = $request->validate([$key => self::FIELDS[$key]]);

        $current = Setting::get($key);
        if ((string) $current === (string) $data[$key]) {
            return back(); // guard the no-op: no write, no log entry
        }

        DB::transaction(function () use ($key, $data, $current, $changeLog) {
            Setting::set($key, $data[$key]);
            $changeLog->logSettingsUpdated([$key => $current], [$key => $data[$key]]);
        });

        return back()->with('success', __('messages.admin.settings_saved'));
    }

    public function update(Request $request, ChangeLogService $changeLog)
    {
        $data = $request->validate(self::FIELDS);

        /*
         * 🔴 Drop any sensitive field that came back as its own mask. The form
         * posts EVERY field it rendered, so an admin who edits the shipping fee
         * and saves would otherwise overwrite the IBAN with a row of dots — and
         * nothing would error. Every bank-transfer customer would then be given
         * `••••8130` to pay into.
         *
         * Dropping rather than rejecting: leaving a field untouched is exactly
         * what the admin meant by not revealing it.
         */
        foreach (SensitiveSettings::KEYS as $key) {
            if (array_key_exists($key, $data) && SensitiveSettings::isMask($data[$key])) {
                unset($data[$key]);
            }
        }

        // Normalise the boolean toggles to a clean '1'/'0' string (a raw PHP false
        // would persist as '' and read back as "on").
        foreach (self::BOOLEAN_FIELDS as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $request->boolean($key) ? '1' : '0';
            }
        }

        // 🔴 Refuse up front rather than let the last method be switched off. The
        // failure would otherwise surface days later as a shopper unable to pay at
        // all, with nothing connecting it back to this save — the same shape as the
        // last-carrier guard on the shipping portal.
        if ($this->wouldDisableEveryPaymentMethod($data)) {
            return back()->with('error', __('messages.admin.payment_last_enabled'));
        }

        DB::transaction(function () use ($data, $changeLog) {
            $old = [];
            $new = [];
            foreach ($data as $key => $value) {
                $current = Setting::get($key);
                if ((string) $current !== (string) $value) { // guard no-op writes
                    $old[$key] = $current;
                    $new[$key] = $value;
                    Setting::set($key, $value);
                }
            }

            $changeLog->logSettingsUpdated($old, $new); // one entry per save, changed keys only
        });

        return back()->with('success', __('messages.admin.settings_saved'));
    }

    /**
     * Would this save leave checkout with no way to pay?
     *
     * Reads the submitted value where the form sent one and the stored value
     * otherwise, because the settings form posts whichever fields it rendered —
     * a partial save must be judged on the resulting state, not on this request
     * alone.
     *
     * @param  array<string,mixed>  $data
     */
    private function wouldDisableEveryPaymentMethod(array $data): bool
    {
        foreach (PaymentMethod::cases() as $method) {
            $key = $method->settingKey();
            $enabled = array_key_exists($key, $data)
                ? $data[$key] === '1'
                : Setting::get($key, '1') === '1';

            if ($enabled) {
                return false;
            }
        }

        return true;
    }
}
