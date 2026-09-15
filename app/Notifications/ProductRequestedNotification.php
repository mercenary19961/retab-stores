<?php

namespace App\Notifications;

use App\Models\ProductRequest;
use App\Notifications\Concerns\SendsStaffMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Staff alert that a customer tapped "I want this" on a Coming-Soon product:
 * bell row + email. See NewOrderNotification for why the database payload is
 * structured while the email is pre-rendered in Arabic, and why mail is
 * conditional on the recipient actually having an email address.
 */
class ProductRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;
    use SendsStaffMail;

    public function __construct(private ProductRequest $request)
    {
        $this->locale(self::STAFF_LOCALE);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->staffChannels($notifiable);
    }

    /** @return array<string, string> */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->request->product?->name_ar ?? '—';
        $contact = $this->request->user?->name ?? $this->request->phone ?? '—';

        return $this->staffMail(
            subject: __('emails.staff.product_requested.subject'),
            heading: __('emails.staff.product_requested.heading'),
            lines: [__('emails.staff.product_requested.intro')],
            details: [
                __('emails.staff.product') => $product,
                __('emails.staff.contact') => $contact,
            ],
            actionUrl: url('/admin/product-requests'),
            actionLabel: __('emails.staff.product_requested.action'),
            note: __('emails.staff.product_requested.note'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'product_requested',
            'request_id' => $this->request->id,
            'product_id' => $this->request->product_id,
            'product_name' => $this->request->product?->name_ar,
            'contact' => $this->request->user?->name ?? $this->request->phone,
            'url' => '/admin/product-requests',
        ];
    }
}
