<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Contract every payment provider (Moyasar, Tamara, ...) implements.
 *
 * Keeping the rest of the app behind this interface means swapping or adding
 * a gateway later only touches the binding in AppServiceProvider, not the
 * checkout flow, controllers, or webhook handling.
 */
interface PaymentGateway
{
    /**
     * Create a hosted payment session for the order.
     *
     * @return array{url: string, invoice_id: string, raw: array}
     */
    public function createInvoice(Order $order): array;

    /**
     * Fetch a single payment from the provider and normalize it.
     */
    public function fetchPayment(string $paymentId): NormalizedPayment;

    /**
     * Fetch an invoice and the (possibly multiple) payment attempts on it.
     * Used by the reconciliation sweeper to recover from missed webhooks.
     *
     * @return array{status: string, payments: NormalizedPayment[], raw: array}
     */
    public function fetchInvoice(string $invoiceId): array;

    /**
     * Constant-time check that a webhook/callback secret matches ours.
     */
    public function verifyWebhookToken(?string $token): bool;
}
