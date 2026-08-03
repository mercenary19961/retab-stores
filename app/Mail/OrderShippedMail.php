<?php

namespace App\Mail;

/**
 * "Your order is on its way" — sent when a shipment is created with the carrier,
 * carrying the tracking number, alongside the existing WhatsApp notice.
 */
class OrderShippedMail extends OrderMail
{
    protected function translationKey(): string
    {
        return 'shipped';
    }

    protected function viewName(): string
    {
        return 'order-shipped';
    }
}
