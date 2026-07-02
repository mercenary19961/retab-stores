<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Tamara\TamaraService;
use App\Support\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Defect/damage-only returns per the store policy: the customer files in-account
 * with photos within 3 days of delivery (orders.delivered_at); admin inspects and
 * resolves by exchange or refund. Shipping fee is refunded ONLY when the dates
 * arrived damaged. Refunds route to the capturing gateway (card→Moyasar,
 * Tamara→Tamara); bank transfers are refunded manually and only recorded here.
 */
class ReturnService
{
    public const WINDOW_DAYS = 3;

    public function __construct(
        protected PaymentService $payments,
        protected TamaraService $tamara,
        protected \App\Services\WhatsApp\WhatsAppService $whatsapp,
    ) {}

    /**
     * A return may be filed while the order is delivered, inside the 3-day
     * window, and has no prior non-rejected return (one return per order).
     */
    public function canRequest(Order $order): bool
    {
        return $order->status === OrderStatus::Delivered
            && $order->isWithinReturnWindow(self::WINDOW_DAYS)
            && ! $order->returns()->where('status', '!=', ReturnStatus::Rejected->value)->exists();
    }

    /**
     * File a defect/damage return: validates the window + items, stores the
     * photos via Media, and alerts the admins.
     *
     * @param  array<int, array{order_item_id: int, quantity: int}>  $items
     * @param  list<UploadedFile>  $photos
     *
     * @throws RuntimeException on any eligibility/validation failure
     */
    public function fileReturn(Order $order, User $user, array $items, string $reason, array $photos): OrderReturn
    {
        if ($order->user_id !== $user->id) {
            throw new RuntimeException(__('messages.returns.not_yours'));
        }
        if ($order->status !== OrderStatus::Delivered) {
            throw new RuntimeException(__('messages.returns.not_delivered'));
        }
        if (! $order->isWithinReturnWindow(self::WINDOW_DAYS)) {
            throw new RuntimeException(__('messages.returns.window_expired'));
        }
        if ($order->returns()->where('status', '!=', ReturnStatus::Rejected->value)->exists()) {
            throw new RuntimeException(__('messages.returns.already_filed'));
        }

        $orderItems = $order->items()->get()->keyBy('id');
        $lines = collect($items)
            ->filter(fn (array $line) => ($line['quantity'] ?? 0) > 0)
            ->values();

        if ($lines->isEmpty()) {
            throw new RuntimeException(__('messages.returns.no_items'));
        }

        foreach ($lines as $line) {
            $orderItem = $orderItems->get($line['order_item_id']);
            if (! $orderItem || $line['quantity'] > $orderItem->quantity) {
                throw new RuntimeException(__('messages.returns.invalid_items'));
            }
        }

        $paths = array_map(
            fn (UploadedFile $photo) => Media::storeImage($photo, "returns/{$order->id}"),
            $photos,
        );

        $return = DB::transaction(function () use ($order, $user, $lines, $reason, $paths, $orderItems) {
            $return = OrderReturn::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'status' => ReturnStatus::Requested,
                'reason' => $reason,
                'photos' => $paths,
            ]);

            foreach ($lines as $line) {
                $return->items()->create([
                    'order_item_id' => $line['order_item_id'],
                    'product_id' => $orderItems->get($line['order_item_id'])->product_id,
                    'quantity' => $line['quantity'],
                ]);
            }

            return $return;
        });

        $this->whatsapp->notifyAdminsReturnRequested($return);

        return $return;
    }

    /** requested → approved (issue verified from the photos/inspection). */
    public function approve(OrderReturn $return, ?int $adminId, ?string $notes = null): OrderReturn
    {
        $this->assertStatus($return, ReturnStatus::Requested);

        $return->update([
            'status' => ReturnStatus::Approved,
            'admin_notes' => $notes ?? $return->admin_notes,
        ]);

        $this->whatsapp->notifyReturnUpdate($return);

        return $return;
    }

    /** requested → rejected (not a defect / outside policy). */
    public function reject(OrderReturn $return, ?int $adminId, ?string $notes = null): OrderReturn
    {
        $this->assertStatus($return, ReturnStatus::Requested);

        $return->update([
            'status' => ReturnStatus::Rejected,
            'admin_notes' => $notes ?? $return->admin_notes,
            'resolved_at' => now(),
            'resolved_by' => $adminId,
        ]);

        $this->whatsapp->notifyReturnUpdate($return);

        return $return;
    }

    /** approved → exchanged (replacement shipped; no money moves). */
    public function resolveExchange(OrderReturn $return, ?int $adminId, ?string $notes = null): OrderReturn
    {
        $this->assertStatus($return, ReturnStatus::Approved);

        $return->update([
            'status' => ReturnStatus::Exchanged,
            'resolution' => 'exchange',
            'admin_notes' => $notes ?? $return->admin_notes,
            'resolved_at' => now(),
            'resolved_by' => $adminId,
        ]);

        $this->whatsapp->notifyReturnUpdate($return);

        return $return;
    }

    /**
     * approved → refunded. Amount = returned items' value (+ shipping fee only
     * when the goods arrived damaged), refunded through the capturing gateway.
     * Bank transfers move no gateway money — the admin transfers back manually.
     */
    public function resolveRefund(OrderReturn $return, ?int $adminId, bool $refundShipping, ?string $notes = null): OrderReturn
    {
        $this->assertStatus($return, ReturnStatus::Approved);

        $order = $return->order;
        $amount = $this->refundAmount($return, $refundShipping);

        match ($order->payment_method) {
            PaymentMethod::Card => $this->payments->refund($order, $amount),
            PaymentMethod::Tamara => $this->tamara->refund($order, $amount),
            default => null, // bank transfer — manual refund, ledger not involved
        };

        $return->update([
            'status' => ReturnStatus::Refunded,
            'resolution' => 'refund',
            'refund_amount' => $amount,
            'refund_shipping' => $refundShipping,
            'admin_notes' => $notes ?? $return->admin_notes,
            'resolved_at' => now(),
            'resolved_by' => $adminId,
        ]);

        $this->whatsapp->notifyReturnUpdate($return);

        return $return;
    }

    /** Returned items' paid value + optional shipping fee, capped at the order total. */
    public function refundAmount(OrderReturn $return, bool $refundShipping): float
    {
        $return->loadMissing('items.orderItem', 'order');

        $items = $return->items->sum(
            fn ($item) => (float) ($item->orderItem?->unit_price ?? 0) * $item->quantity
        );

        $amount = $items + ($refundShipping ? (float) $return->order->shipping_fee : 0);

        return round(min($amount, (float) $return->order->total), 2);
    }

    private function assertStatus(OrderReturn $return, ReturnStatus $expected): void
    {
        if ($return->status !== $expected) {
            throw new RuntimeException(__('messages.returns.invalid_transition'));
        }
    }
}
