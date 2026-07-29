<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\ReviewRewardService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The post-delivery "write a review, get a discount" WhatsApp nudge. Dispatched
 * with a 1-day delay from ShippingService when an order is delivered. Eligibility
 * is re-checked at SEND time (a day later the feature could be off, or the
 * customer may have already earned the reward), and `review_reminder_sent_at`
 * guards against a repeated "delivered" webhook re-sending it.
 */
class SendReviewReminder implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $orderId,
    ) {}

    public function handle(WhatsAppService $whatsapp, ReviewRewardService $rewards): void
    {
        $order = Order::with(['user', 'items.product'])->find($this->orderId);

        // Account-based, once per order, and only while the reward is still earnable.
        if (! $order || ! $order->user_id || $order->review_reminder_sent_at) {
            return;
        }
        if (! $rewards->availableFor($order->user)) {
            return;
        }

        // Deep-link to a purchased product's review area; fall back to the account.
        $slug = $order->items->first(fn ($item) => $item->product)?->product?->slug;
        $reviewUrl = $slug ? route('shop.product', $slug).'#reviews' : url('/account/orders');

        $whatsapp->notifyReviewReminder($order, $rewards->percent(), $reviewUrl);
        $order->forceFill(['review_reminder_sent_at' => now()])->save();
    }
}
