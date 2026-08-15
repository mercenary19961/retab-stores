<?php

namespace App\Services\Shipping;

use App\Enums\OrderStatus;
use App\Jobs\SendReviewReminder;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Services\ReviewRewardService;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates order fulfillment through a ShippingGateway (OTO). The admin
 * triggers fulfill() after confirming the order; it pushes the order to the
 * aggregator, picks a carrier (cheapest by default, or an admin-chosen option),
 * creates the shipment, and stores the tracking number + carrier + label.
 */
class ShippingService
{
    public function __construct(
        protected ShippingGateway $gateway,
    ) {}

    /**
     * Carrier options + prices for the admin to review before shipping.
     *
     * @return DeliveryOption[]
     */
    public function quote(Order $order): array
    {
        $this->ensureOrderPushed($order);

        return $this->gateway->getDeliveryOptions($order);
    }

    /**
     * Create the shipment. If $deliveryOptionId is null, the cheapest available
     * option is chosen. Idempotency: refuses to ship an order that already has a
     * tracking number.
     */
    public function fulfill(Order $order, ?int $deliveryOptionId = null, ?int $userId = null): Order
    {
        if ($order->tracking_number) {
            throw new \RuntimeException(__('messages.admin.shipment_already_exists'));
        }

        $this->ensureOrderPushed($order);

        // Quote even when the carrier was chosen by hand. The option's PRICE
        // exists nowhere else — createShipment does not report it — and it is
        // the store's real cost, which is the whole point of recording it. The
        // extra call only lands on manual picks (automatic had to quote anyway),
        // and resolving the id out of the live list validates it for free.
        $option = $this->resolveOption($order, $deliveryOptionId);

        $shipment = $this->gateway->createShipment($order, $option->id);

        $order->forceFill([
            'shipping_provider' => 'oto',
            'tracking_number' => $shipment->trackingNumber,
            'carrier' => $shipment->carrier,
            // What the carrier charges US. `shipping_fee` is the flat rate the
            // CUSTOMER paid; the gap between them is the absorbed margin.
            'shipping_cost' => $option->price,
            'shipping_label_url' => $shipment->labelUrl,
            'oto_id' => $shipment->otoId ?? $order->oto_id,
            'status' => OrderStatus::Shipped,
        ])->save();

        OrderActivity::logTrackingUpdate(
            $order,
            $shipment->trackingNumber,
            $shipment->carrier,
            $userId,
            $option->price,
            $option->currency,
        );

        return $order;
    }

    /**
     * Recall the shipment and return the order to `confirmed` so it can be
     * shipped again — typically to switch carrier. Moves no money: the order is
     * still live and still owed to the customer, so refunding the shipping fee
     * here would be wrong (cancelling the ORDER is a separate path with its own
     * refund).
     *
     * `oto_id` is deliberately KEPT, so the follow-up fulfil() reuses the order
     * already pushed to OTO instead of trying to create a duplicate under the
     * same order number.
     *
     * ⚠️ UNVERIFIED against a live parcel: whether OTO's cancelShipment voids
     * only the shipment or the whole order on their side. If it is the latter,
     * the re-ship will fail at createShipment and the admin will see OTO's error
     * — recoverable, but it would mean clearing `oto_id` here so the order gets
     * re-pushed. Confirm on the first real cancellation before trusting the
     * re-ship path in a client runbook.
     */
    public function cancel(Order $order, ?int $userId = null): Order
    {
        if (! $order->tracking_number) {
            throw new \RuntimeException(__('messages.admin.shipment_missing'));
        }

        $this->gateway->cancelShipment($order);

        // Captured before the write, because the whole value of this log entry is
        // recording WHICH shipment was recalled — and the columns are about to
        // be cleared.
        $recalled = [
            'tracking_number' => $order->tracking_number,
            'carrier' => $order->carrier,
            'cost' => $order->shipping_cost,
        ];
        $old = $order->status->value;

        $order->forceFill([
            'tracking_number' => null,
            'carrier' => null,
            'shipping_label_url' => null,
            // The recalled shipment's cost no longer describes this order; a
            // re-ship writes the new one. The figure survives on the activity
            // entry, so a cancelled-then-reshipped order can still be audited
            // for double carrier charges.
            'shipping_cost' => null,
            'status' => OrderStatus::Confirmed,
        ])->save();

        OrderActivity::logShipmentCancelled($order, $old, $recalled, $userId);

        return $order;
    }

    /**
     * Apply a shipment status update from the aggregator webhook to the order.
     * Returns the order, or null if it can't be matched.
     */
    public function applyStatusUpdate(string $orderNumber, string $providerStatus): ?Order
    {
        $order = Order::where('order_number', $orderNumber)->first();
        if (! $order) {
            Log::warning('OTO status update for unknown order', ['order_number' => $orderNumber]);

            return null;
        }

        $mapped = $this->mapStatus($providerStatus);
        if ($mapped && $order->status !== $mapped) {
            $old = $order->status->value;

            $attributes = ['status' => $mapped];
            // Delivery starts the 3-day return window.
            if ($mapped === OrderStatus::Delivered) {
                $attributes['delivered_at'] = now();
            }

            $order->forceFill($attributes)->save();
            OrderActivity::logStatusChange($order, $old, $mapped->value, null);

            // Delivered → queue the "write a review, get a discount" WhatsApp nudge
            // for ~1 day later. The job re-checks eligibility at send time; we only
            // bother queueing for account orders while the feature is on.
            if ($mapped === OrderStatus::Delivered && $order->user_id && app(ReviewRewardService::class)->enabled()) {
                SendReviewReminder::dispatch($order->id)->delay(now()->addDay());
            }
        }

        return $order;
    }

    private function ensureOrderPushed(Order $order): void
    {
        if ($order->oto_id) {
            return;
        }

        $otoId = $this->gateway->pushOrder($order);
        $order->forceFill(['oto_id' => $otoId, 'shipping_provider' => 'oto'])->save();
    }

    /**
     * The carrier option to ship with: the admin's explicit choice, or the
     * cheapest when they left it on automatic.
     *
     * Returns the whole DeliveryOption rather than its id so the caller gets the
     * price with it — that figure is only available from a quote and is what
     * makes the store's real shipping cost recordable.
     */
    private function resolveOption(Order $order, ?int $deliveryOptionId): DeliveryOption
    {
        $options = $this->gateway->getDeliveryOptions($order);

        if ($options === []) {
            throw new \RuntimeException(__('messages.admin.no_delivery_options'));
        }

        usort($options, fn (DeliveryOption $a, DeliveryOption $b) => $a->price <=> $b->price);

        if ($deliveryOptionId === null) {
            return $options[0];
        }

        foreach ($options as $option) {
            if ($option->id === $deliveryOptionId) {
                return $option;
            }
        }

        // Rates are live, so a picker left open long enough can offer an option
        // OTO no longer honours. Say so plainly instead of passing a dead id
        // through and surfacing whatever OTO answers.
        throw new \RuntimeException(__('messages.admin.delivery_option_unavailable'));
    }

    /**
     * Map a provider status onto ours, or null to ignore it (OTO emits plenty of
     * intermediate states like `shipmentProcessing` that we deliberately skip).
     *
     * Separators are stripped rather than spelled out, because OTO's documented
     * payloads are camelCase (`shipmentProcessing`) while the original list here
     * mixed both conventions — it carried `pickedup` and `intransit` but only
     * `out_for_delivery`, so a real `outForDelivery` callback fell through to
     * null and left the order sitting at confirmed. Normalising means we no
     * longer have to guess a provider's casing at all.
     */
    private function mapStatus(string $providerStatus): ?OrderStatus
    {
        $normalized = preg_replace('/[^a-z0-9]/', '', strtolower($providerStatus));

        return match ($normalized) {
            'delivered' => OrderStatus::Delivered,
            'shipped', 'pickedup', 'outfordelivery', 'intransit' => OrderStatus::Shipped,
            'cancelled', 'canceled', 'returned' => OrderStatus::Cancelled,
            default => null,
        };
    }
}
