<?php

namespace App\Support;

/**
 * Turn a phone number as a customer typed it into the international digits
 * WhatsApp needs (E.164 without the '+').
 *
 * 🔴 Stripping non-digits is not enough, and it is what every WhatsApp path did
 * until 2026-09-15: checkout accepts a local Saudi number, so `0512345678` was
 * sent to Meta as-is and a wa.me link opened no chat. A Saudi mobile written
 * locally (05XXXXXXXX, or 5XXXXXXXX) gains the 966 country code; a `00` prefix
 * is dropped. Anything else is already international and passes through, so no
 * other GCC number is guessed at.
 *
 * ⚠️ Deliberately NOT used by OtpService::normalize(), which keys login codes and
 * accounts on its output. Changing that would split an existing account in two.
 */
class PhoneNumber
{
    /**
     * Could we actually reach this number?
     *
     * Two shapes are accepted, and the split is deliberate:
     *
     *   1. A SAUDI MOBILE written locally — `05XXXXXXXX` or `5XXXXXXXX`. Saudi
     *      mobiles are nine significant digits beginning with 5, which is exactly
     *      what toWhatsApp() below already encodes; the two must agree, or we
     *      would accept a number we then cannot message.
     *   2. An EXPLICITLY INTERNATIONAL number — one the customer prefixed with
     *      `+` or `00`, of 8 to 15 digits (E.164 caps at 15).
     *
     * 🔑 Requiring the `+`/`00` for case 2 is what makes `4343443434` fail. Ten
     * digits starting with 4 is not a Saudi mobile, and without a country code it
     * is not an international number either — it is a typo. Accepting any bare
     * run of digits, which is what `max:20` did, is the same as no rule at all.
     *
     * ⚠️ The field's `+966` prefix is chrome, not part of the value (see
     * SaudiPhoneField), so a GCC customer has to type their own country code.
     * That is a real limitation and it is the honest one: a bare `501234567`
     * cannot be both a Saudi and an Emirati number, and silently guessing Saudi
     * would send the order confirmation to a stranger.
     */
    public static function isValid(?string $phone): bool
    {
        $raw = trim((string) $phone);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return false;
        }

        // Already carrying Saudi's country code, however it was written.
        if (preg_match('/^(00)?9665\d{8}$/', $digits) === 1) {
            return true;
        }

        // Local Saudi mobile.
        if (preg_match('/^0?5\d{8}$/', $digits) === 1) {
            return true;
        }

        // Anything else must SAY it is international; length per E.164.
        $international = str_starts_with($raw, '+') || str_starts_with($digits, '00');

        return $international && preg_match('/^\d{8,15}$/', ltrim($digits, '0')) === 1;
    }

    public static function toWhatsApp(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (preg_match('/^05\d{8}$/', $digits)) {
            $digits = '966'.substr($digits, 1);
        } elseif (preg_match('/^5\d{8}$/', $digits)) {
            $digits = '966'.$digits;
        }

        return $digits === '' ? null : $digits;
    }

    /** A click-to-chat link, or null when there is no usable number. */
    public static function whatsAppUrl(?string $phone): ?string
    {
        $digits = self::toWhatsApp($phone);

        return $digits === null ? null : 'https://wa.me/'.$digits;
    }
}
