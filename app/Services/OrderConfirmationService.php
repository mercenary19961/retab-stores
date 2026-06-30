<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\DemandEvent;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Models\Product;
use App\Services\Payments\Tamara\TamaraService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The admin confirm / unavailable / cancel flow — the core of the order lifecycle.
 *
 * Stock is deducted HERE (at confirmation), never at checkout, because website
 * stock is advisory until the SMACC sync and the human confirm-step is the real
 * backstop. OTO pickup + WhatsApp are best-effort steps the caller runs AFTER a
 * successful confirm (they're external and must not roll back the confirmation).
 */
class OrderConfirmationService
{
    /**
     * The loyalty reward issued by the most recent confirm(), or null. Lets the
     * caller (admin OrderController) fire the loyalty WhatsApp without coupling
     * this service — or LoyaltyService — to the messaging layer.
     */
    public ?\App\Models\LoyaltyReward $issuedReward = null;

    public function __construct(
        protected TamaraService $tamara,
        protected LoyaltyService $loyalty,
    ) {}

    /**
     * Admin confirms: capture Tamara (if BNPL; cards already paid), deduct stock,
     * and move awaiting_confirmation → confirmed. Row-locked + idempotent.
     */
    public function confirm(Order $order, ?int $userId = null): Order
    {
        if ($order->status !== OrderStatus::AwaitingConfirmation) {
            throw new \RuntimeException('Order is not awaiting confirmation.');
        }

        // Tamara holds are captured at confirmation; card payments captured at checkout.
        if ($order->payment_method === PaymentMethod::Tamara && $order->payment_status === PaymentStatus::Authorized) {
            $this->tamara->capture($order);
            $order->refresh();
        }

        DB::transaction(function () use ($order, $userId) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if ($locked->status !== OrderStatus::AwaitingConfirmation) {
                return; // already confirmed by a concurrent call
            }

            $locked->loadMissing('items');
            foreach ($locked->items as $item) {
                if ($item->product_id) {
                    Product::whereKey($item->product_id)->decrement('stock', $item->quantity);
                }
            }

            $locked->forceFill([
                'status' => OrderStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by' => $userId,
            ])->save();

            OrderActivity::logStatusChange(
                $locked,
                OrderStatus::AwaitingConfirmation->value,
                OrderStatus::Confirmed->value,
                $userId,
            );
        });

        $order->refresh();

        // Count the confirmed purchase toward loyalty (issues the 5→15% reward).
        $this->issuedReward = $this->loyalty->recordConfirmedPurchase($order);

        return $order;
    }

    /**
     * Admin can't fulfill (out of stock). Releases the held funds, logs demand
     * analytics, and flips the order to `unavailable`. No stock was deducted, so
     * there's nothing to restore.
     */
    public function markUnavailable(Order $order, ?int $userId = null, ?string $note = null): Order
    {
        if ($order->status !== OrderStatus::AwaitingConfirmation) {
            throw new \RuntimeException('Order is not awaiting confirmation.');
        }

        $this->releaseFunds($order);

        DB::transaction(function () use ($order, $userId, $note) {
            $order->loadMissing('items');
            foreach ($order->items as $item) {
                DemandEvent::create([
                    'product_id' => $item->product_id,
                    'order_id' => $order->id,
                    'customer_phone' => $order->customer_phone,
                    'action' => 'unavailable',
                    'occurred_at' => now(),
                ]);
            }

            $order->forceFill([
                'status' => OrderStatus::Unavailable,
                'admin_notes' => $note ?? $order->admin_notes,
            ])->save();

            OrderActivity::logStatusChange(
                $order,
                OrderStatus::AwaitingConfirmation->value,
                OrderStatus::Unavailable->value,
                $userId,
            );
        });

        return $order->refresh();
    }

    /**
     * Customer cancellation — allowed ONLY before the admin confirms.
     */
    public function cancelByCustomer(Order $order): Order
    {
        if (! $order->status->isCancellableByCustomer()) {
            throw new \RuntimeException('This order can no longer be cancelled.');
        }

        $this->releaseFunds($order);

        $from = $order->status->value;
        $order->forceFill([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ])->save();

        OrderActivity::logStatusChange($order, $from, OrderStatus::Cancelled->value, null);

        return $order;
    }

    /**
     * Void a Tamara hold, or flag a captured card payment for refund. (Card
     * refunds go through the Moyasar refund API — wired via PaymentService::refund.)
     */
    private function releaseFunds(Order $order): void
    {
        if ($order->payment_method === PaymentMethod::Tamara && $order->payment_status === PaymentStatus::Authorized) {
            $this->tamara->void($order);

            return;
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            // TODO: PaymentService::refund — captured card needs a real Moyasar refund.
            Log::info('Card refund required', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        }
    }
}
