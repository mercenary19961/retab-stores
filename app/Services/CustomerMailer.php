<?php

namespace App\Services;

use App\Mail\OrderConfirmedMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderMail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderShippedMail;
use App\Mail\OrderUnavailableMail;
use App\Mail\ReturnUpdateMail;
use App\Models\Order;
use App\Models\OrderReturn;
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
     * The stock check failed and the order is being refunded.
     *
     * 🔴 The one customer email that must not be missed: the refund itself
     * arrives from the gateway days later with no explanation attached, so
     * without this the customer only sees money moving and an order that
     * stopped existing.
     */
    public function orderUnavailable(Order $order): bool
    {
        return $this->send($order, new OrderUnavailableMail($order));
    }

    /** The carrier reported delivery — which is when the return window opens. */
    public function orderDelivered(Order $order): bool
    {
        return $this->send($order, new OrderDeliveredMail($order));
    }

    /**
     * A return moved to a new state.
     *
     * ⚠️ Guards on the RETURN'S OWN ORDER, not on an order passed in, so the
     * address and the language always come from the order the return belongs to.
     */
    public function returnUpdate(OrderReturn $return): bool
    {
        $return->loadMissing('order');

        if (! $return->order) {
            return false;
        }

        return $this->send($return->order, new ReturnUpdateMail($return));
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
