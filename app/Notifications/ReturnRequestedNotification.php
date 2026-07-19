<?php

namespace App\Notifications;

use App\Models\OrderReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * In-panel (admin bell) alert that a customer filed a defect/damage return.
 * Stored via the `database` channel only. See NewOrderNotification for why the
 * payload is structured rather than pre-rendered text.
 */
class ReturnRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(private OrderReturn $return) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
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
