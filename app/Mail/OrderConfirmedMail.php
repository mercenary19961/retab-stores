<?php

namespace App\Mail;

/**
 * "Your order is confirmed" — sent when staff confirm stock and accept the order,
 * alongside the existing WhatsApp confirmation.
 */
class OrderConfirmedMail extends OrderMail
{
    protected function translationKey(): string
    {
        return 'confirmed';
    }

    protected function viewName(): string
    {
        return 'order-confirmed';
    }
}
