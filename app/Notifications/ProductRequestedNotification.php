<?php

namespace App\Notifications;

use App\Models\ProductRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Staff alert that a customer tapped "I want this" on a Coming-Soon product:
 * bell row + email. See NewOrderNotification for why the database payload is
 * structured while the email is pre-rendered English, and why mail is
 * conditional on the recipient actually having an email address.
 */
class ProductRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private ProductRequest $request) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
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

        return (new MailMessage)
            ->subject('Someone wants a coming-soon product')
            ->line("A customer registered interest in: {$product}")
            ->line("Contact: {$contact}")
            ->action('Open product requests', url('/admin/product-requests'))
            ->line('Follow up on WhatsApp, then mark the request handled.');
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
