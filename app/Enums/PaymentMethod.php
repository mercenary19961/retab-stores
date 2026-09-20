<?php

namespace App\Enums;

use App\Models\Setting;

enum PaymentMethod: string
{
    case Card = 'card';                   // Moyasar (mada / Visa / Mastercard / Apple Pay) — captured at checkout
    case Tamara = 'tamara';               // BNPL — authorized at checkout, captured on confirmation
    case BankTransfer = 'bank_transfer';  // manual transfer to the store IBAN, admin-verified

    /** Settings key holding whether the store currently offers this method. */
    public function settingKey(): string
    {
        return 'payment_'.$this->value.'_enabled';
    }

    /**
     * The methods a shopper may choose at checkout right now.
     *
     * 🔴 This is the ONLY definition. The checkout page renders from it and the
     * checkout request validates against it, so a disabled method cannot be
     * posted by hand after being hidden in the browser.
     *
     * ⚠️ It governs STARTING a payment, never finishing one. An order already
     * placed with a method the store has since switched off must still be
     * payable — `CheckoutController::pay()` reads `$order->payment_method` and
     * deliberately does not consult this list, or turning a gateway off would
     * strand every customer mid-payment.
     *
     * Unset defaults to enabled, so adding this feature changed nothing for a
     * store that had never seen the setting.
     *
     * @return list<self>
     */
    public static function enabled(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $method) => Setting::get($method->settingKey(), '1') === '1',
        ));
    }

    /** @return list<string> */
    public static function enabledValues(): array
    {
        return array_map(fn (self $method) => $method->value, self::enabled());
    }
}
