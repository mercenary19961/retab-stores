<?php

namespace App\Notifications;

use App\Models\OrderReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Staff alert that a customer filed a defect/damage return: bell row + email.
 * See NewOrderNotification for why the database payload is structured while the
 * email is pre-rendered English, and why mail is conditional on the recipient
 * actually having an email address.
 */
class ReturnRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private OrderReturn $return) {}

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
        $number = $this->return->order?->order_number ?? '—';

        return (new MailMessage)
            ->subject("Return requested for order {$number}")
            ->line("A customer filed a return for order {$number}.")
            ->line('Reason: '.Str::limit((string) $this->return->reason, 200))
            ->action('Review the return', url("/admin/returns/{$this->return->id}"))
            ->line('Returns are defect/damage only and must be filed within 3 days of delivery — the photos are on the review page.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'return_requested',
            'return_id' => $this->return->id,
            'order_number' => $this->return->order?->order_number,
            'reason' => Str::limit((string) $this->return->reason, 80),
            'url' => "/admin/returns/{$this->return->id}",
        ];
    }
}
