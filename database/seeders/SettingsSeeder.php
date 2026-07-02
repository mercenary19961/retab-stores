<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\CheckoutService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, 25); // single flat GCC shipping fee (SAR)
        Setting::set('store_name_ar', 'رطاب للتمور');
        Setting::set('store_name_en', 'Retab Dates');
        Setting::set('store_email', 'info@retab.com.sa');
        Setting::set('store_phone', '+966550883845');
        Setting::set('returns_whatsapp', '+966503845356'); // returns dept (from the policy)
        Setting::set('low_stock_threshold', 5);

        // Legal entity + bank-transfer receiving account (from the client's current site).
        Setting::set('legal_name', 'شركة مصنع رطاب الوطن للتمور');
        Setting::set('bank_name', 'مصرف الراجحي');
        Setting::set('bank_beneficiary', 'شركة مصنع رطاب الوطن للتمور');
        Setting::set('bank_account', '145608010008130');
        Setting::set('bank_iban', 'SA9780000145608010008130');
    }
}
