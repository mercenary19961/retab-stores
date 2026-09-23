<?php

namespace App\Mail;

/**
 * "We could not fulfil your order" — sent when staff mark an order unavailable
 * after the stock check, alongside the existing WhatsApp notice.
 *
 * 🔴 This is the most important of the customer emails, and it was the one with
 * no email at all. It is the only message that explains why money is going back
 * to someone who thought they had bought something — the refund itself arrives
 * silently from the gateway days later. Until WhatsApp is live this was a
 * customer being refunded with no explanation on any channel.
 */
class OrderUnavailableMail extends OrderMail
{
    protected function translationKey(): string
    {
        return 'unavailable';
    }

    protected function viewName(): string
    {
        return 'order-unavailable';
    }

    /**
     * ⚠️ Carries NO reason, deliberately, matching the WhatsApp template which
     * sends only the name and order number.
     *
     * The note staff type when marking an order unavailable is APPENDED TO
     * `admin_notes` — the same field they keep their own running commentary in,
     * documented as such in OrderConfirmationService. Forwarding it would mail
     * the customer whatever internal remarks happen to be on the order. If a
     * customer-facing reason is ever wanted it needs its own column, not this one.
     */
}
