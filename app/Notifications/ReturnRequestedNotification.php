<?php

namespace App\Notifications;

use App\Models\OrderReturn;
use App\Notifications\Concerns\SendsStaffMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Staff alert that a customer filed a defect/damage return: bell row + email.
 * See NewOrderNotification for why the database payload is structured while the
 * email is pre-rendered in Arabic, and why mail is conditional on the recipient
 * actually having an email address.
 */
class ReturnRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;
    use SendsStaffMail;

    public function __construct(private OrderReturn $return)
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
        $number = $this->return->order?->order_number ?? '—';

        return $this->staffMail(
            subject: __('emails.staff.return_requested.subject', ['number' => $number]),
            heading: __('emails.staff.return_requested.heading'),
            lines: [__('emails.staff.return_requested.intro', ['number' => $number])],
            details: [
                __('emails.staff.order_number') => $number,
                __('emails.staff.return_requested.reason') => Str::limit((string) $this->return->reason, 500),
            ],
            actionUrl: url("/admin/returns/{$this->return->id}"),
            actionLabel: __('emails.staff.return_requested.action'),
            note: __('emails.staff.return_requested.note'),
        );
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
