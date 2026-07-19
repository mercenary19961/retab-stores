<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-panel (admin bell) alert that a new order needs attention. Stored via the
 * `database` channel only — WhatsApp/email are dispatched separately.
 *
 * The payload is STRUCTURED (order number + total + type), not pre-rendered
 * prose: the admin bell renders the title/message client-side through i18n so
 * it follows the admin's language toggle (the panel is EN-first with an AR
 * switch, independent of the server session locale).
 */
class NewOrderNotification extends Notification
{
    use Queueable;

    public function __construct(private Order $order) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
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
