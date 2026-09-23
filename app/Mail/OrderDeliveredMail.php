<?php

namespace App\Mail;

use App\Services\ReturnService;

/**
 * "Your order has been delivered" — sent when the carrier reports delivery.
 *
 * 🔑 It exists to start a clock, not to celebrate. Delivery opens the 3-day
 * window in which a damaged or defective order can be returned, and that window
 * is measured from `delivered_at` whether or not the customer knows it has
 * begun. Telling them is the difference between a policy and a trap.
 */
class OrderDeliveredMail extends OrderMail
{
    protected function translationKey(): string
    {
        return 'delivered';
    }

    protected function viewName(): string
    {
        return 'order-delivered';
    }

    /**
     * How long the customer has to report a problem.
     *
     * Read from ReturnService, which is the constant the eligibility check
     * itself uses — so the promise in this email cannot drift from the rule
     * that will be applied when they act on it.
     */
    protected function extraData(): array
    {
        return ['returnDays' => ReturnService::WINDOW_DAYS];
    }
}
