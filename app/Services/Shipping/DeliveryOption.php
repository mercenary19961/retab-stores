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
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'carrier' => $this->carrier,
            'price' => $this->price,
            'currency' => $this->currency,
            'estimated_delivery' => $this->estimatedDelivery,
        ];
    }
}
