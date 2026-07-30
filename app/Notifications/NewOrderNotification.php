<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Staff alert that a new order needs attention: an in-panel bell row plus an
 * email. WhatsApp is dispatched separately (WhatsAppService), because it goes to
 * a configured phone list rather than to the staff user records.
 *
 * The database payload is STRUCTURED (order number + total + type), not
 * pre-rendered prose: the admin bell renders the title/message client-side
 * through i18n so it follows the admin's language toggle (the panel is EN-first
 * with an AR switch, independent of the server session locale).
 *
 * ⚠️ The email, by contrast, IS pre-rendered — and deliberately in English. The
 * admin's chosen panel language lives in their browser (localStorage +
 * `admin_locale` cookie), so a notification triggered by a CUSTOMER's request
 * has no way to know it; English matches the EN-first admin panel.
 */
class NewOrderNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private Order $order) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // `users.email` is NULLABLE here (a staff account can be phone-only under
        // the OTP identity model) and the mail transport throws on an empty
        // address — so email is opt-in per recipient, not assumed.
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    /**
     * Keep the bell row on the `sync` connection so it lands the instant the
     * order does (no worker in the loop); only the email is queued, off the
     * customer's checkout request.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;

        return (new MailMessage)
            ->subject("New order {$number} needs confirmation")
            ->line('A new order has been placed and is waiting for your confirmation.')
            ->line("Order: {$number}")
            ->line('Total: '.number_format((float) $this->order->total, 2).' SAR')
            ->action('Open the order', url("/admin/orders/{$number}"))
            ->line('Check stock, then confirm or reject it. Card payments are already captured and Tamara authorizations expire, so please review within 24 hours.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_order',
            'order_number' => $this->order->order_number,
            'total' => number_format((float) $this->order->total, 2),
            'currency' => 'SAR',
            'url' => "/admin/orders/{$this->order->order_number}",
        ];
    }
}
