<?php

namespace App\Mail;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Setting;

/**
 * "We received your order" — the receipt, sent the moment checkout completes.
 *
 * This is the highest-value email in the set because of BANK TRANSFER: for that
 * method the IBAN and the reference number the customer must quote exist only on
 * the confirmation page, which is gone as soon as they close the tab. Card and
 * Tamara orders get the same receipt without the transfer block.
 */
class OrderPlacedMail extends OrderMail
{
    protected function translationKey(): string
    {
        return 'placed';
    }

    protected function viewName(): string
    {
        return 'order-placed';
    }

    protected function extraData(): array
    {
        return ['bank' => $this->bankDetails()];
    }

    /**
     * Transfer instructions, only while they're actionable — i.e. a bank-transfer
     * order that hasn't been paid yet. Mirrors the same condition on the
     * confirmation page (CheckoutController::confirmation).
     *
     * @return array<string, string|null>|null
     */
    private function bankDetails(): ?array
    {
        if ($this->order->payment_method !== PaymentMethod::BankTransfer
            || $this->order->payment_status !== PaymentStatus::Pending) {
            return null;
        }

        return [
            'bank_name' => Setting::get('bank_name'),
            'beneficiary' => Setting::get('bank_beneficiary'),
            'account' => Setting::get('bank_account'),
            'iban' => Setting::get('bank_iban'),
        ];
    }
}
