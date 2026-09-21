<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A phone number we could actually reach.
 *
 * 🔴 Until 2026-09-21 no phone in this app was format-checked ANYWHERE — every
 * controller used `['required', 'string', 'max:20']`, so `4343443434`, `123` and
 * `asdfghjkl` were all accepted. That is worse here than on a typical store,
 * because the phone is not a nice-to-have: WhatsApp is the primary channel for
 * order confirmation, the courier calls it on delivery, and under the OTP
 * identity model it can be the account itself. An unreachable number is a
 * shipment nobody can complete.
 *
 * 🔑 The rule delegates entirely to PhoneNumber::isValid(), so the definition of
 * "reachable" lives in ONE place next to the normaliser that has to agree with
 * it. A regex copied into four controllers is how the checkout and the login end
 * up disagreeing about what a phone is.
 */
class Phone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! PhoneNumber::isValid(is_string($value) ? $value : null)) {
            $fail('validation.phone_number')->translate();
        }
    }
}
