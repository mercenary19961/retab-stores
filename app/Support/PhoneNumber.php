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
