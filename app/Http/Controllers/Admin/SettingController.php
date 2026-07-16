<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ChangeLog\ChangeLogService;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ]);
    }

    public function update(Request $request, ChangeLogService $changeLog)
    {
        $data = $request->validate(self::FIELDS);

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
