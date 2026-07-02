<?php

namespace App\Services\Payments;

/**
 * Provider-agnostic shape of a payment, so the rest of the app never has to
 * know a gateway's exact JSON. Each gateway maps its response into this.
 */
class NormalizedPayment
{
    public function __construct(
        public readonly string $id,
        /** Normalized to one of: paid, failed, authorized, pending, refunded, voided */
        public readonly string $status,
        /** Amount in minor units (halalas). */
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $sourceType = null,
        public readonly ?string $sourceCompany = null,
        public readonly ?string $invoiceId = null,
        /** order_id pulled from gateway metadata, if present. */
        public readonly ?int $orderId = null,
        public readonly ?string $failureMessage = null,
        public readonly array $raw = [],
    ) {}

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isAuthorized(): bool
    {
        return $this->status === 'authorized';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'voided'], true);
    }
}
