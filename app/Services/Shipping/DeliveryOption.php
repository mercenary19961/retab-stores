<?php

namespace App\Services\Shipping;

/**
 * Provider-agnostic shape of one carrier option returned when quoting a
 * shipment (e.g. "Naqel — 23.00 SAR — 1 to 2 Working Days"). Used internally
 * for carrier selection; the customer always pays the flat shipping fee.
 */
class DeliveryOption
{
    public function __construct(
        public readonly int $id,
        public readonly string $carrier,
        public readonly float $price,
        public readonly string $currency = 'SAR',
        public readonly ?string $estimatedDelivery = null,
        public readonly array $raw = [],
        /**
         * The specific service within the carrier, e.g. "SMSA PUDO" vs "SMSA".
         * Load-bearing rather than decorative: one company can quote several
         * services at different prices under a single company name, so without
         * this the picker shows rows that are indistinguishable to the operator.
         */
        public readonly ?string $service = null,
        /** True when the customer must collect the parcel — see PickupPoint. */
        public readonly bool $pickupDropoff = false,
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
            'pickup_dropoff' => $this->pickupDropoff,
        ];
    }
}
