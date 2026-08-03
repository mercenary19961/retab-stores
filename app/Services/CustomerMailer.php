<?php

namespace App\Services;

use App\Mail\OrderConfirmedMail;
use App\Mail\OrderMail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderShippedMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Customer-facing transactional email.
 *
 * Shaped after WhatsAppService: the call sites just say what happened, and every
 * "can we actually reach this customer?" decision lives here, once. Each method
 * returns whether a mail was queued so callers can assert on it rather than
 * guessing.
 *
 * ⚠️ `orders.customer_email` is NULLABLE by design — the identity model allows a
 * phone-only account (WhatsApp OTP sign-in) and guest checkout only requires a
 * phone. Those customers are reached over WhatsApp instead, which is why a
 * missing address is a normal no-op here and not an error.
 *
 * ⚠️ Sending is QUEUED, so nothing arrives without a running `queue:work`. That
 * is already a documented deploy prerequisite for staff email and WhatsApp; this
 * adds customer email to what a missing worker silently swallows.
 */
class CustomerMailer
{
    public function orderPlaced(Order $order): bool
    {
        return $this->send($order, new OrderPlacedMail($order));
    }

    public function orderConfirmed(Order $order): bool
    {
        return $this->send($order, new OrderConfirmedMail($order));
    }

    public function orderShipped(Order $order): bool
    {
        return $this->send($order, new OrderShippedMail($order));
    }

    /**
     * Queue one order mail, guarding on a usable address.
     *
     * Failures are swallowed and logged on purpose: this runs on the customer's
     * checkout request and inside admin state transitions, and neither may break
     * because a mail provider hiccupped. (Queue dispatch rarely throws, but a
     * misconfigured mailer or a serialization error would surface right here.)
     */
    private function send(Order $order, OrderMail $mail): bool
    {
        $address = $this->addressFor($order);

        if (! $address) {
            return false;
        }

        try {
            Mail::to($address)->queue($mail);

            return true;
        } catch (\Throwable $e) {
            Log::error('Customer order email failed to queue', [
                'order' => $order->order_number,
                'mail' => $mail::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Prefer the address captured ON THE ORDER: it's the snapshot of what the
     * customer typed at checkout, which is where they expect the receipt — the
     * account address may be older or absent. Falls back to the account.
     */
    private function addressFor(Order $order): ?string
    {
        $address = $order->customer_email ?: $order->user?->email;

        return filter_var($address, FILTER_VALIDATE_EMAIL) ? $address : null;
    }
}
