<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use App\Notifications\Concerns\SendsStaffMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

/**
 * Staff alert that a customer submitted the Contact Us form: bell row + email. See
 * NewOrderNotification for why the database payload is structured while the email
 * is pre-rendered in Arabic, and why mail is conditional on the recipient actually
 * having an email address.
 *
 * Originally shipped with no admin page and no `url`, on the reasoning that staff
 * reply directly (WhatsApp/email) rather than through a workflow. That was wrong
 * in practice: the payload names the sender but NOT what they wrote, so a click
 * fell back to `/admin/dashboard` and the message text existed only in the email —
 * unreadable from the panel. `/admin/contact-messages` now owns that, and `url`
 * deep-links to it.
 *
 * The body still isn't in the payload, deliberately: notification rows are stored
 * forever and duplicating a 2000-char message into every staff member's row (as a
 * snapshot that can never be corrected) is the wrong place for it. The page reads
 * the live record instead.
 */
class ContactMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;
    use SendsStaffMail;

    public function __construct(private ContactMessage $contactMessage)
    {
        $this->locale(self::STAFF_LOCALE);
    }

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

        $type = (string) $this->contactMessage->inquiry_type;
        // A type added to the form but not yet to the translations still shows,
        // as its raw key, rather than as "emails.staff.inquiry_types.x".
        $typeLabel = Lang::has("emails.staff.inquiry_types.{$type}") ? __("emails.staff.inquiry_types.{$type}") : $type;

        return $this->staffMail(
            subject: __('emails.staff.contact_message.subject'),
            heading: __('emails.staff.contact_message.heading'),
            details: [
                __('emails.staff.contact_message.name') => $name,
                __('emails.staff.contact_message.email') => $this->contactMessage->email,
                __('emails.staff.contact_message.phone') => $this->contactMessage->phone,
                __('emails.staff.contact_message.inquiry_type') => $typeLabel,
                __('emails.staff.contact_message.message') => $this->contactMessage->message,
            ],
            actionUrl: url('/admin/contact-messages'),
            actionLabel: __('emails.staff.contact_message.action'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'contact_message_received',
            'contact_message_id' => $this->contactMessage->id,
            'url' => '/admin/contact-messages',
            'name' => trim("{$this->contactMessage->first_name} {$this->contactMessage->last_name}"),
            'email' => $this->contactMessage->email,
            'phone' => $this->contactMessage->phone,
            'inquiry_type' => $this->contactMessage->inquiry_type,
        ];
    }
}
