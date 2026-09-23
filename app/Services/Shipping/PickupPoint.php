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
 * 🔴 THE FIELD TO READ IS `deliveryType`, NOT `pickupDropoff`, and getting that
 * backwards was a live bug. OTO's `pickupDropoff` is a STRING enum describing the
 * FIRST mile — whether the courier collects from the MERCHANT for free or the
 * merchant must drop off at the courier's branch — and it was being read as
 * `(bool)`. Its four values are `freePickup`, `dropoffOnly`, `freePickupDropoff`
 * and `lockerDropOff`; every one of them is a truthy string, so EVERY carrier was
 * flagged as a pickup point. preferredOption() then found no door delivery at
 * all, fell through to its cheapest-overall fallback, and picked the 13.92 SMSA
 * counter — the exact failure this class exists to prevent.
 *
 * ⚠️ Deliberately still an OR of the structured field and the name, not "trust
 * the field, fall back to the name". The two failure directions are not
 * symmetric: a false positive only costs the store a cheap option it can still
 * choose by hand, while a false negative silently strands a customer. And
 * `deliveryType` is only confirmed present in the rate-check payload — the richer
 * getDeliveryOptions endpoint is plan-gated and unavailable on this account, so
 * on that path the service name may be all there is.
 */
class PickupPoint
{
    /**
     * `deliveryType` values that mean the CUSTOMER collects.
     *
     * Read off a live rate-check response, not from documentation: the full set
     * observed is `toCustomerDoorstep` (16 of 20 rows), `pickupByCustomer` (the
     * three PUDO services) and `locker` (Redbox). Compared with punctuation and
     * case stripped, so a future `pickup_by_customer` still matches.
     *
     * ⚠️ Anything unrecognised falls through to the name check rather than being
     * treated as door delivery, which is the safe direction.
     */
    private const COLLECTION_TYPES = ['pickupbycustomer', 'locker'];

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
     * @param  string|null  $deliveryType  OTO's `deliveryType`, when the payload carries it.
     * @param  string|null  ...$names  Service and carrier names, in any order.
     */
    public static function detect(?string $deliveryType, ?string ...$names): bool
    {
        if (in_array(self::normalise($deliveryType), self::COLLECTION_TYPES, true)) {
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

    /** Lowercased with every separator removed, so casing and style cannot matter. */
    private static function normalise(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower((string) $value)) ?? '';
    }
}
