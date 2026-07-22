<?php

namespace App\Notifications;

use App\Models\ProductRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-panel (admin bell) alert that a customer tapped "I want this" on a
 * Coming-Soon product. Stored via the `database` channel only. See
 * NewOrderNotification for why the payload is structured rather than pre-rendered.
 */
class ProductRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(private ProductRequest $request) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'product_requested',
            'request_id' => $this->request->id,
            'product_id' => $this->request->product_id,
            'product_name' => $this->request->product?->name_ar,
            'contact' => $this->request->user?->name ?? $this->request->phone,
            'url' => '/admin/product-requests',
        ];
    }
}
