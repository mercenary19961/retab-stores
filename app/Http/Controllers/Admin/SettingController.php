<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
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
    ];

    public function edit()
    {
        return Inertia::render('admin/settings/index', [
            'settings' => collect(array_keys(self::FIELDS))
                ->mapWithKeys(fn (string $key) => [$key => Setting::get($key)]),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(self::FIELDS);

        foreach ($data as $key => $value) {
            if ((string) Setting::get($key) !== (string) $value) { // guard no-op writes
                Setting::set($key, $value);
            }
        }

        return back()->with('success', __('messages.admin.settings_saved'));
    }
}
