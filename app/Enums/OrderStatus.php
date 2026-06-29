<?php

namespace App\Enums;

/**
 * Fulfillment lifecycle of an order. Payment state is tracked separately in
 * {@see PaymentStatus}. Transitions are enforced via canTransitionTo().
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';           // created, awaiting payment
    case AwaitingConfirmation = 'awaiting_confirmation'; // paid/authorized, awaiting admin inventory check
    case Confirmed = 'confirmed';                       // admin confirmed → stock deducted, OTO pickup requested
    case Shipped = 'shipped';                           // picked up / in transit
    case Delivered = 'delivered';                       // delivered (starts the 3-day return window)
    case Cancelled = 'cancelled';                       // cancelled by customer (pre-confirm) or admin
    case Unavailable = 'unavailable';                   // admin could not fulfill (out of stock) → apology + refund/void

    /**
     * The states this status is allowed to move to.
     *
     * @return self[]
     */
    public function transitionsTo(): array
    {
        return match ($this) {
            self::PendingPayment => [self::AwaitingConfirmation, self::Cancelled],
            self::AwaitingConfirmation => [self::Confirmed, self::Unavailable, self::Cancelled],
            self::Confirmed => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled, self::Unavailable => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitionsTo(), true);
    }

    public function isTerminal(): bool
    {
        return $this->transitionsTo() === [];
    }

    /**
     * Customer may cancel only before the admin confirms the order.
     */
    public function isCancellableByCustomer(): bool
    {
        return in_array($this, [self::PendingPayment, self::AwaitingConfirmation], true);
    }
}
