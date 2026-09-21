<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The settings that identify the business financially.
 *
 * 🔴 These are not merely "private". The IBAN is the account customers pay into
 * for every bank-transfer order, so a silently altered one redirects real money
 * to somebody else, and nothing in the order flow would notice: the receipt
 * email, the order page and the admin all read the same setting, so they would
 * agree with each other and all be wrong.
 *
 * That is what the masking and the confirmed delete are actually defending
 * against. Masking alone is only shoulder-surfing cover; the email confirmation
 * is the part that costs an attacker something they cannot get from a stolen
 * session.
 */
class SensitiveSettings
{
    /** @var list<string> */
    public const KEYS = [
        'bank_account',
        'bank_iban',
        'commercial_registration',
        'vat_number',
    ];

    public static function isSensitive(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    /**
     * What the admin sees before revealing: the last four characters, everything
     * before them replaced.
     *
     * 🔑 The tail rather than the head, because the tail is what a human uses to
     * recognise their own account ("...8130") while the head of an IBAN is the
     * country and bank code, which identifies the bank rather than the account.
     *
     * ⚠️ A short value is masked ENTIRELY. Revealing the last four of a
     * five-character value gives away almost all of it, which would make the
     * mask worse than useless by implying protection it is not providing.
     */
    public static function mask(?string $value): string
    {
        $value = (string) $value;
        $length = mb_strlen($value);

        if ($length === 0) {
            return '';
        }

        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        return str_repeat('•', $length - 4).mb_substr($value, -4);
    }

    /**
     * The values as the settings page should first render them: sensitive ones
     * masked, everything else as stored.
     *
     * @param  array<string,mixed>  $settings
     * @return array<string,mixed>
     */
    public static function maskAll(array $settings): array
    {
        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $settings)) {
                $settings[$key] = self::mask($settings[$key] === null ? null : (string) $settings[$key]);
            }
        }

        return $settings;
    }

    /**
     * Is this submitted value just the mask coming back?
     *
     * 🔴 THE MOST IMPORTANT GUARD IN THIS CLASS. The settings form posts every
     * field it rendered, so an admin who changes the shipping fee and saves would
     * otherwise write `••••8130` into the IBAN and destroy it. Every customer's
     * bank-transfer instructions would then be a row of dots, and nothing would
     * error. Pinned by a test.
     */
    public static function isMask(?string $value): bool
    {
        $value = (string) $value;

        return $value !== '' && preg_match('/^•+/u', $value) === 1;
    }

    /** Clear every sensitive value. Used only by the confirmed deletion. */
    public static function purge(): void
    {
        foreach (self::KEYS as $key) {
            Setting::set($key, '');
        }
    }

    /** Are any of them actually set? Nothing to delete otherwise. */
    public static function anyPresent(): bool
    {
        foreach (self::KEYS as $key) {
            if (filled(Setting::get($key))) {
                return true;
            }
        }

        return false;
    }
}
