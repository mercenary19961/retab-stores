<?php

namespace App\Enums;

/**
 * How the customer gets their order.
 *
 * ⚠️ `Collection` is the customer coming to the shop themselves. It is NOT one
 * of OTO's PUDO counters — `App\Services\Shipping\PickupPoint` covers those, and
 * there a courier still carries the parcel. Keeping the two words apart matters,
 * because the automatic carrier pick already has to reason about PUDO services.
 */
enum Fulfillment: string
{
    case Delivery = 'delivery';      // shipped by a carrier to the address on the order
    case Collection = 'collection';  // customer collects from the Al Malqa shop

    /**
     * Does this order need shipping at all?
     *
     * 🔑 The one question the rest of the app should ask. Collection means no
     * carrier, no shipping fee and no address — so checkout skips the address
     * block, the flat fee is not charged, and the admin's Ship action has
     * nothing to do.
     */
    public function needsShipping(): bool
    {
        return $this === self::Delivery;
    }
}
