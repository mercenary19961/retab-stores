<?php

namespace App\Notifications;

use App\Models\Order;
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
 * email is pre-rendered English, and why mail is conditional on the recipient
 * having an email address at all.
 */
class PaymentExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private Order $order, private int $hoursLeft) {}

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
        return (new MailMessage)
            ->subject("Action needed: order {$this->order->order_number} expires in ~{$this->hoursLeft}h")
            ->line("The Tamara authorisation on order {$this->order->order_number} lapses in roughly {$this->hoursLeft} hours.")
            ->line('Total: '.number_format((float) $this->order->total, 2).' SAR')
            ->action('Confirm or reject the order', url("/admin/orders/{$this->order->id}"))
            ->line('Confirming captures the money. Rejecting releases the hold cleanly. Doing nothing loses the sale.');
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
            'url' => "/admin/orders/{$this->order->id}",
        ];
    }
}
