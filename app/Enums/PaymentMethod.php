<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';                   // Moyasar (mada / Visa / Mastercard / Apple Pay) — captured at checkout
    case Tamara = 'tamara';               // BNPL — authorized at checkout, captured on confirmation
    case BankTransfer = 'bank_transfer';  // manual transfer to the store IBAN, admin-verified
}
