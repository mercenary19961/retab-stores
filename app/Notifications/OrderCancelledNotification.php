<?php

namespace App\Notifications;

use App\Models\Order;
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
 * email is pre-rendered English, and why mail is conditional on the recipient
 * actually having an email address (users.email is nullable under the OTP
 * identity model, and the mail transport throws on an empty one).
 */
class OrderCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private Order $order) {}

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
            ->subject("Order {$this->order->order_number} was cancelled by the customer")
            ->line("{$this->order->customer_name} cancelled order {$this->order->order_number}.")
            ->line('Total: '.number_format((float) $this->order->total, 2).' SAR')
            ->action('Open the order', url("/admin/orders/{$this->order->id}"))
            ->line('Stop any preparation for it. Any payment has already been released automatically.');
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
            'url' => "/admin/orders/{$this->order->id}",
        ];
    }
}
