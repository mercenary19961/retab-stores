<?php

namespace App\Mail;

use App\Models\OrderReturn;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Your return request has been updated" — sent on every step of a return,
 * alongside the existing WhatsApp notice.
 *
 * 🔴 All four return touchpoints (filed, approved, rejected, resolved) were
 * WhatsApp-only, so with WhatsApp not yet live a customer could send photos of a
 * damaged order and then hear nothing at all — through the approval, through the
 * decision, and through the refund.
 *
 * 🔑 Extends OrderMail for the language pin rather than for convenience. Return
 * mail is queued like the rest, so at render time the worker's locale is Arabic
 * regardless of the customer; the order carries the language snapshotted at
 * checkout, and a return always belongs to an order.
 */
class ReturnUpdateMail extends OrderMail
{
    public function __construct(public OrderReturn $return)
    {
        $return->loadMissing('order');

        parent::__construct($return->order);
    }

    protected function translationKey(): string
    {
        return 'return_update';
    }

    protected function viewName(): string
    {
        return 'return-update';
    }

    /**
     * Named for the RETURN, not the items.
     *
     * The base subject leads with what was bought, which is right for an order
     * mail and wrong here: several of these can arrive for one order as the
     * request moves through its states, and four identical "…: شابورة بالبر"
     * subjects would be unreadable in an inbox. The status is what changed, so
     * the status is what the subject says.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.return_update.subject', [
                'status' => __('emails.return_update.statuses.'.$this->return->status->value),
            ])." ({$this->order->order_number})",
        );
    }

    protected function extraData(): array
    {
        return [
            'return' => $this->return,
            'statusLabel' => __('emails.return_update.statuses.'.$this->return->status->value),
            // The line that tells them what to expect next — approved means wait
            // for the resolution, refunded means watch their statement, and so on.
            'statusNote' => __('emails.return_update.notes.'.$this->return->status->value),
        ];
    }
}
