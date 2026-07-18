<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\CheckoutService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::defaults() as $key => $value) {
            Setting::set($key, $value);
        }
    }

    /**
     * The project-handover settings values. Single source of truth for both the
     * seeder and the admin "reset to handover defaults" safeguard.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            CheckoutService::SHIPPING_FEE_KEY => 25, // single flat GCC shipping fee (SAR)
            'store_name_ar' => 'رطاب للتمور',
            'store_name_en' => 'Retab Dates',
            'store_email' => 'info@retab.com.sa',
            'store_phone' => '+966550883845',
            'returns_whatsapp' => '+966503845356', // returns dept (from the policy)
            'low_stock_threshold' => 5,

            // Legal entity + bank-transfer receiving account (from the client's current site).
            'legal_name' => 'شركة مصنع رطاب الوطن للتمور',
            'bank_name' => 'مصرف الراجحي',
            'bank_beneficiary' => 'شركة مصنع رطاب الوطن للتمور',
            'bank_account' => '145608010008130',
            'bank_iban' => 'SA9780000145608010008130',

            // Admin UX default: the "How it works" attention beam is on at handover.
            'admin_help_pulse' => '1',
        ];
    }
}
