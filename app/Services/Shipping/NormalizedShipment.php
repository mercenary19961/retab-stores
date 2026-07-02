<?php

namespace App\Services\Shipping;

/**
 * Provider-agnostic result of creating a shipment.
 */
class NormalizedShipment
{
    public function __construct(
        public readonly string $trackingNumber,
        public readonly string $carrier,
        public readonly ?string $labelUrl = null,
        public readonly ?int $otoId = null,
        public readonly array $raw = [],
    ) {}
}
