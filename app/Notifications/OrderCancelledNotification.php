<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\SendsStaffMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Staff alert that a CUSTOMER cancelled their own order before it was
 * confirmed: bell row + email.
 *
 * 🔑 This exists because staff have to find out somehow, and WhatsApp is not
 * the answer on its own — the WhatsApp fan-out needs credentials that are not
 * configured yet, whereas the bell writes synchronously and always works. It
 * also matters more than a new-order alert in one respect: someone may already
 * be picking stock for this order.
 *
 * See NewOrderNotification for why the database payload is structured while the
 * email is pre-rendered in Arabic, and why mail is conditional on the recipient
 * actually having an email address (users.email is nullable under the OTP
 * identity model, and the mail transport throws on an empty one).
 */
class OrderCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;
    use SendsStaffMail;

    public function __construct(private Order $order)
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
        $number = $this->order->order_number;

        return $this->staffMail(
            subject: __('emails.staff.order_cancelled.subject', ['number' => $number]),
            heading: __('emails.staff.order_cancelled.heading'),
            lines: [__('emails.staff.order_cancelled.intro', ['name' => $this->order->customer_name, 'number' => $number])],
            details: [
                __('emails.staff.order_number') => $number,
                __('emails.staff.total') => $this->money($this->order->total),
            ],
            // By ORDER NUMBER: the admin route binds `{order:order_number}`, so the
            // id this used to link to was a 404.
            actionUrl: url("/admin/orders/{$number}"),
            actionLabel: __('emails.staff.open_order'),
            note: __('emails.staff.order_cancelled.note'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_cancelled',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'customer' => $this->order->customer_name,
            'total' => (float) $this->order->total,
            'url' => "/admin/orders/{$this->order->order_number}",
        ];
    }
}
