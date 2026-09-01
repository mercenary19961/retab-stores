<?php

namespace App\Services\Shipping;

/**
 * Is this shipping service a pickup point — "PUDO", pick up / drop off — where
 * the customer collects the parcel from a branch, counter or locker rather than
 * having it brought to their door?
 *
 * It matters because it is materially cheaper (SMSA quotes 13.92 against 24.36
 * SAR for the same destination), which is exactly what would make the automatic
 * cheapest-carrier pick choose it — handing a customer who paid the flat
 * home-delivery fee an errand instead of a delivery.
 *
 * 🔑 ONE definition, read by both the admin portal (which badges it) and the
 * quote path (which keeps it off the automatic pick). Restating the rule in
 * either place is precisely how the two would come to disagree about the same
 * service.
 *
 * ⚠️ Deliberately an OR of the flag and the name, not "trust the flag, fall back
 * to the name". The two failure directions are not symmetric: a false positive
 * only costs the store a cheap option it can still choose by hand, while a false
 * negative silently strands a customer. And OTO's structured flag is absent
 * entirely from the rate-check payload, where the service name is all there is.
 */
class PickupPoint
{
    /**
     * Phrases that unambiguously mean "the customer collects it".
     *
     * 🔴 Keep this list TIGHT, for the same reason ShippingCarrier::NOISE is kept
     * tight: every term added risks catching an ordinary door-delivery service
     * and quietly removing it from the automatic pick. "collect" is deliberately
     * absent — it would swallow "collect on delivery".
     */
    private const HINTS = ['pudo', 'pickup point', 'pick up point', 'drop off point', 'locker'];

    /**
     * @param  bool|null  $flag  OTO's own `pickupDropoff`, when the payload carries it.
     * @param  string|null  ...$names  Service and carrier names, in any order.
     */
    public static function detect(?bool $flag, ?string ...$names): bool
    {
        if ($flag === true) {
            return true;
        }

        foreach ($names as $name) {
            if ($name === null || trim($name) === '') {
                continue;
            }

            // Collapse punctuation and case so "SMSA-PUDO", "SMSA PUDO" and
            // "smsa_pudo" all reduce to the same words.
            $clean = trim(preg_replace('/[^a-z0-9]+/i', ' ', strtolower($name)) ?? '');

            // Padded whole-phrase matching, never a bare substring: a plain
            // str_contains for "pudo" would also fire on a courier whose name
            // merely happens to contain those four letters.
            $padded = ' '.$clean.' ';

            foreach (self::HINTS as $hint) {
                if (str_contains($padded, ' '.$hint.' ')) {
                    return true;
                }
            }
        }

        return false;
    }
}
