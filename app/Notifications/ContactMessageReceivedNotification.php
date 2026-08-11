<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Staff alert that a customer submitted the Contact Us form: bell row + email. See
 * NewOrderNotification for why the database payload is structured while the email
 * is pre-rendered English, and why mail is conditional on the recipient actually
 * having an email address.
 *
 * ⚠️ Deliberately NO dedicated admin page (unlike product requests / returns).
 * There is no `url` in the payload, so the bell's own fallback sends a click to
 * `/admin/dashboard` rather than a 404 — see `Admin\NotificationController::open()`.
 * The email carries the full message body and the customer's own contact details,
 * since staff reply directly (WhatsApp/email), not through an admin workflow. If
 * volume ever warrants a queue/inbox, add the page then rather than building one
 * ahead of the need.
 */
class ContactMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private ContactMessage $contactMessage) {}

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
        $name = trim("{$this->contactMessage->first_name} {$this->contactMessage->last_name}");

        return (new MailMessage)
            ->subject('New contact form message')
            ->line("From: {$name}")
            ->line("Email: {$this->contactMessage->email}")
            ->line("Phone: {$this->contactMessage->phone}")
            ->line("Inquiry type: {$this->contactMessage->inquiry_type}")
            ->line('Message:')
            ->line($this->contactMessage->message);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'contact_message_received',
            'contact_message_id' => $this->contactMessage->id,
            'name' => trim("{$this->contactMessage->first_name} {$this->contactMessage->last_name}"),
            'email' => $this->contactMessage->email,
            'phone' => $this->contactMessage->phone,
            'inquiry_type' => $this->contactMessage->inquiry_type,
        ];
    }
}
