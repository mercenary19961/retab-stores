<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Shared plumbing for the customer-facing order emails.
 *
 * ⚠️ Language is the whole reason this base class exists. These are QUEUED, so at
 * render time `app()->getLocale()` is the WORKER's locale (AR, the app default) —
 * not the customer's. Every mail therefore pins `$this->locale` to the language
 * snapshotted on the order at checkout (`orders.locale`); Laravel wraps the
 * envelope AND the view render in that locale, so the subject line follows too.
 *
 * This is the deliberate inverse of the STAFF notifications, which are hard-coded
 * English because the admin's panel language lives in their browser and a
 * customer-triggered alert can't know it (see NewOrderNotification).
 */
abstract class OrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->locale($order->locale ?: config('app.locale'));
    }

    /** Translation key prefix for this email, e.g. `placed` → `emails.placed.*`. */
    abstract protected function translationKey(): string;

    /**
     * Blade view under `emails/`, e.g. `order-placed`.
     *
     * ⚠️ NOT `view()` — Mailable already defines a public fluent `view()` setter,
     * so an abstract override of that name is a signature clash.
     */
    abstract protected function viewName(): string;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __("emails.{$this->translationKey()}.subject", ['number' => $this->order->order_number]),
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing('items');

        return new Content(
            view: "emails.{$this->viewName()}",
            with: array_merge([
                'order' => $this->order,
                'items' => $this->order->items,
                'locale' => $this->localeOrDefault(),
                'storeName' => $this->storeName(),
                'supportPhone' => Setting::get('store_phone'),
                'orderUrl' => $this->orderUrl(),
            ], $this->extraData()),
        );
    }

    /** Per-email view data on top of the shared set. */
    protected function extraData(): array
    {
        return [];
    }

    protected function localeOrDefault(): string
    {
        return $this->order->locale ?: (string) config('app.locale');
    }

    protected function storeName(): string
    {
        $key = $this->localeOrDefault() === 'en' ? 'store_name_en' : 'store_name_ar';

        return (string) (Setting::get($key) ?: Setting::get('store_name_ar') ?: config('app.name'));
    }

    /**
     * Only link registered customers to their order page. The storefront gates
     * `/orders/{number}` on session state or ownership, so a guest opening this
     * email later — different browser, days on — would land on a 403. The email
     * body carries everything they need (items, totals, and for bank transfer the
     * IBAN + reference), so omitting the button beats sending them to an error.
     */
    protected function orderUrl(): ?string
    {
        return $this->order->user_id
            ? route('orders.show', $this->order->order_number)
            : null;
    }
}
