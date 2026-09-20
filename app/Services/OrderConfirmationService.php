<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionType;
use App\Models\DemandEvent;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Payments\PaymentService;
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
    public ?LoyaltyReward $issuedReward = null;

    public function __construct(
        protected TamaraService $tamara,
        protected LoyaltyService $loyalty,
        protected PaymentService $payments,
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
                    // Shared stock deducts by base units: an option that consumes
                    // N base units per purchase (a 500g = 2, a carton = N) deducts
                    // N × quantity. Plain products snapshot stock_units = 1.
                    Product::whereKey($item->product_id)
                        ->decrement('stock', max(1, (int) $item->stock_units) * $item->quantity);
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
     * Staff saw a bank transfer arrive: record it and hand the order on to the
     * normal confirm step (pending_payment → awaiting_confirmation).
     *
     * 🔴 Before this existed nothing could make that move. Cards advance on the
     * Moyasar webhook and Tamara on its authorisation, but a transfer has no
     * gateway to report it, so every bank-transfer order stayed in "pending
     * payment" until someone cancelled it.
     *
     * Row-locked and idempotent, so a double click (or two staff members at once)
     * records the money once. Nothing is sent to the customer: their receipt with
     * the bank details went out at checkout, and the confirmation message follows
     * when staff confirm the order.
     */
    public function markTransferReceived(Order $order, ?int $userId = null, ?string $reference = null): Order
    {
        if (! $order->isAwaitingBankTransfer()) {
            throw new \RuntimeException(__('messages.admin.transfer_not_applicable'));
        }

        DB::transaction(function () use ($order, $userId, $reference) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked->isAwaitingBankTransfer()) {
                return; // recorded by a concurrent call
            }

            $from = $locked->status->value;

            $locked->forceFill([
                'payment_status' => PaymentStatus::Paid,
                'status' => OrderStatus::AwaitingConfirmation,
                'paid_at' => now(),
            ])->save();

            // The ledger is the authoritative record of money moving, so a
            // transfer belongs in it like any gateway capture. The bank's own
            // reference, when staff type it, is what reconciliation matches on.
            Payment::create([
                'order_id' => $locked->id,
                'gateway' => PaymentMethod::BankTransfer->value,
                'type' => PaymentTransactionType::Capture,
                'amount' => $locked->total,
                'currency' => $locked->currency,
                'status' => 'succeeded',
                'gateway_transaction_id' => $reference,
                'raw' => ['recorded_by' => $userId],
            ]);

            OrderActivity::logPaymentReceived(
                $locked,
                $from,
                PaymentMethod::BankTransfer->value,
                $locked->total,
                $locked->currency,
                $reference,
            )->forceFill(['user_id' => $userId])->save();
        });

        return $order->refresh();
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

            // Appended, not written over: staff can now keep their own notes on
            // the order, and the reason it could not be filled belongs beside
            // them rather than in place of them.
            $order->forceFill([
                'status' => OrderStatus::Unavailable,
                'admin_notes' => filled($note)
                    ? trim(($order->admin_notes ? $order->admin_notes."\n\n" : '').$note)
                    : $order->admin_notes,
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
            // ⚠️ Localized: this reaches a STOREFRONT customer, and the store is
            // Arabic-first. It was a hardcoded English literal.
            throw new \RuntimeException(__('messages.orders.not_cancellable'));
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
            if ($order->payment_gateway === 'tamara') {
                $this->tamara->refund($order, (float) $order->total);

                return;
            }

            // Captured card → full Moyasar refund. Best-effort: a gateway hiccup
            // must not block the unavailable-flow; it's flagged for manual retry.
            try {
                $this->payments->refund($order, (float) $order->total);
            } catch (\Throwable $e) {
                Log::error('Card refund failed — refund manually from the Moyasar dashboard', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
