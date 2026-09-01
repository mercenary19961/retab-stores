<?php

namespace App\Services\Shipping;

/**
 * One shipping SERVICE a carrier offers, as listed for the admin portal — e.g.
 * "SMSA · Next Day", "SPL · PUDO". OTO calls these delivery options.
 *
 * ⚠️ Not to be confused with DeliveryOption, which is the quote for ONE specific
 * order and carries only what is needed to ship it. This is the catalogue view:
 * the same carrier, described richly enough for someone to choose between
 * couriers — cut-off time, weight allowance, return fee, logo.
 *
 * Every field past `carrier` is nullable because OTO's payload varies by carrier
 * and by which endpoint answered (the rate-check fallback returns far less than
 * the full listing). The portal renders what it has and stays quiet about the
 * rest, rather than showing a grid of dashes.
 */
class CarrierOption
{
    public function __construct(
        public readonly ?int $id,
        /** The courier company, e.g. "SMSA Express" — what the enable toggle keys on. */
        public readonly string $carrier,
        /** The specific service, e.g. "Next Day" or "PUDO". Null when unnamed. */
        public readonly ?string $service = null,
        public readonly ?float $price = null,
        public readonly string $currency = 'SAR',
        public readonly ?string $estimatedDelivery = null,
        public readonly ?string $pickupCutOff = null,
        public readonly ?string $logo = null,
        public readonly ?float $maxOrderValue = null,
        public readonly ?float $maxFreeWeight = null,
        public readonly ?float $extraWeightPerKg = null,
        public readonly ?float $returnFee = null,
        /**
         * True when the customer collects the parcel from a branch, counter or
         * locker instead of it being delivered to their door. Derived by
         * PickupPoint from OTO's flag AND the service name, because the flag is
         * missing from some payloads — never read raw from one field.
         */
        public readonly ?bool $pickupDropoff = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'carrier' => $this->carrier,
            'service' => $this->service,
            'price' => $this->price,
            'currency' => $this->currency,
            'estimated_delivery' => $this->estimatedDelivery,
            'pickup_cut_off' => $this->pickupCutOff,
            'logo' => $this->logo,
            'max_order_value' => $this->maxOrderValue,
            'max_free_weight' => $this->maxFreeWeight,
            'extra_weight_per_kg' => $this->extraWeightPerKg,
            'return_fee' => $this->returnFee,
            'pickup_dropoff' => $this->pickupDropoff,
        ];
    }
}
