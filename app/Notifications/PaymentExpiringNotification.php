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
 * Staff alert that a Tamara authorisation is about to lapse.
 *
 * 🔑 This is the only notification in the app where NOT acting costs money.
 * Tamara holds the funds for a fixed window and we capture at admin
 * confirmation; if the window closes first the hold is gone, the order can
 * never be captured, and the sale is simply lost with nothing to show for it.
 *
 * See NewOrderNotification for why the database payload is structured while the
 * email is pre-rendered in Arabic, and why mail is conditional on the recipient
 * having an email address at all.
 */
class PaymentExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;
    use SendsStaffMail;

    public function __construct(private Order $order, private int $hoursLeft)
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
        // Arabic counts hours in four forms (ساعتين / 5 ساعات / 12 ساعة), so the
        // figure goes through trans_choice rather than a bare number + word.
        $hours = trans_choice('emails.staff.payment_expiring.hours', $this->hoursLeft, ['count' => $this->hoursLeft]);

        return $this->staffMail(
            subject: __('emails.staff.payment_expiring.subject', ['number' => $number, 'hours' => $hours]),
            heading: __('emails.staff.payment_expiring.heading'),
            lines: [__('emails.staff.payment_expiring.intro', ['number' => $number, 'hours' => $hours])],
            details: [
                __('emails.staff.order_number') => $number,
                __('emails.staff.total') => $this->money($this->order->total),
            ],
            // By ORDER NUMBER: the admin route binds `{order:order_number}`, so the
            // id this used to link to was a 404.
            actionUrl: url("/admin/orders/{$number}"),
            actionLabel: __('emails.staff.payment_expiring.action'),
            note: __('emails.staff.payment_expiring.note'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_expiring',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'hours_left' => $this->hoursLeft,
            'total' => (float) $this->order->total,
            'url' => "/admin/orders/{$this->order->order_number}",
        ];
    }
}
