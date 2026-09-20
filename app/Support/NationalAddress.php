<?php

namespace App\Support;

/**
 * The Saudi National Address short code — العنوان الوطني المختصر.
 *
 * Four letters then four digits, e.g. `RRMD7708` (which is the code OTO holds
 * for our own Al Malqa shop). The letters encode the region and city, the digits
 * the building; together they resolve to one entrance, which is why Saudi
 * couriers prefer it to a typed street name.
 *
 * ⚠️ We validate the SHAPE, never the existence. Only SPL's own service can say
 * whether a code is real, and refusing a correctly-shaped code because we cannot
 * reach a third party would block a checkout over something optional.
 */
class NationalAddress
{
    /** Four letters then four digits, case-insensitive, spaces and dashes ignored. */
    public const PATTERN = '/^[A-Za-z]{4}\d{4}$/';

    /**
     * Tidy a code as typed into the stored form, or null when there is nothing
     * usable. Customers type these with spaces ("RRMD 7708"), in lower case, and
     * occasionally with a dash — all of which are the same address.
     */
    public static function normalize(?string $code): ?string
    {
        $clean = strtoupper(preg_replace('/[\s\-]+/', '', (string) $code) ?? '');

        return $clean === '' ? null : $clean;
    }

    /** Is this the right shape? Blank counts as valid — the field is optional. */
    public static function isValid(?string $code): bool
    {
        $clean = self::normalize($code);

        return $clean === null || preg_match(self::PATTERN, $clean) === 1;
    }
}
