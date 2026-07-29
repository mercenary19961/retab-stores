<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientReview;
use App\Models\ContentPage;
use App\Models\Setting;
use App\Services\ChangeLog\ChangeLogService;
use App\Services\CheckoutService;
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
            'settings' => collect(array_keys(self::FIELDS))
                ->mapWithKeys(fn (string $key) => [$key => Setting::get($key)]),
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

    public function update(Request $request, ChangeLogService $changeLog)
    {
        $data = $request->validate(self::FIELDS);

        // Normalise the boolean toggle to a clean '1'/'0' string (a raw PHP false
        // would persist as '' and read back as "on").
        if (array_key_exists('admin_help_pulse', $data)) {
            $data['admin_help_pulse'] = $request->boolean('admin_help_pulse') ? '1' : '0';
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
}
