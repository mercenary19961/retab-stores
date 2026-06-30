<?php

namespace App\Services\WhatsApp;

use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * High-level WhatsApp messaging. Builds the order-lifecycle notifications from the
 * client brief (order confirmed / apology / courier coming / loyalty / new-order
 * admin alert), records EVERY attempt to the whatsapp_messages ledger, and never
 * throws into the caller — a failed message must never roll back a confirmed order.
 *
 * Template names below must match the Meta-approved templates. Inside the 24h
 * customer window free text is allowed; business-initiated messages here are all
 * templates (Utility), per Meta's rules.
 */
class WhatsAppService
{
    // Customer-facing Utility templates.
    public const T_ORDER_CONFIRMED = 'order_confirmed';

    public const T_ORDER_UNAVAILABLE = 'order_unavailable';

    public const T_ORDER_SHIPPED = 'order_shipped';

    public const T_LOYALTY_REWARD = 'loyalty_reward';

    // Internal admin alert.
    public const T_ADMIN_NEW_ORDER = 'admin_new_order';

    public function __construct(
        protected WhatsAppGateway $gateway,
    ) {}

    /** Admin confirmed the order — stock deducted, courier on the way. */
    public function notifyOrderConfirmed(Order $order): ?WhatsappMessage
    {
        return $this->dispatch($order->customer_phone, self::T_ORDER_CONFIRMED, [
            $order->customer_name ?? '',
            $order->order_number,
        ], purpose: 'order_confirm', order: $order);
    }

    /** Out of stock — apology + "back soon". */
    public function notifyOrderUnavailable(Order $order): ?WhatsappMessage
    {
        return $this->dispatch($order->customer_phone, self::T_ORDER_UNAVAILABLE, [
            $order->customer_name ?? '',
            $order->order_number,
        ], purpose: 'apology', order: $order);
    }

    /** Shipment created — courier coming, here's the tracking. */
    public function notifyOrderShipped(Order $order): ?WhatsappMessage
    {
        return $this->dispatch($order->customer_phone, self::T_ORDER_SHIPPED, [
            $order->customer_name ?? '',
            $order->order_number,
            $order->tracking_number ?? '',
        ], purpose: 'shipped', order: $order);
    }

    /** 5-purchase milestone — 15% reward coupon issued. */
    public function notifyLoyaltyReward(Order $order, LoyaltyReward $reward): ?WhatsappMessage
    {
        $code = $reward->coupon?->code ?? '';

        return $this->dispatch($order->customer_phone, self::T_LOYALTY_REWARD, [
            $order->customer_name ?? '',
            $code,
        ], purpose: 'loyalty', order: $order, userId: $reward->user_id);
    }

    /** New order needs attention — alert every configured admin recipient. */
    public function notifyAdminsNewOrder(Order $order): void
    {
        foreach ($this->adminRecipients() as $recipient) {
            $this->dispatch($recipient, self::T_ADMIN_NEW_ORDER, [
                $order->order_number,
                number_format((float) $order->total, 2),
                $order->payment_method?->value ?? '',
            ], purpose: 'admin_new_order', order: $order, category: 'utility');
        }
    }

    /**
     * Apply a delivery/read status from the Meta webhook to the matching ledger
     * row. Only advances forward through sent → delivered → read; ignores unknowns.
     */
    public function updateStatusFromWebhook(string $wamId, string $status): ?WhatsappMessage
    {
        $allowed = ['sent', 'delivered', 'read', 'failed'];
        if (! in_array($status, $allowed, true)) {
            return null;
        }

        $message = WhatsappMessage::where('wam_id', $wamId)->first();
        if (! $message) {
            return null;
        }

        $message->update(['status' => $status]);

        return $message;
    }

    /**
     * Record + send one template message. Always persists a ledger row first
     * (status=queued), then flips to sent/failed. Swallows transport errors.
     *
     * @param  list<string>  $params
     */
    private function dispatch(
        ?string $to,
        string $template,
        array $params,
        string $purpose,
        ?Order $order = null,
        ?int $userId = null,
        string $category = 'utility',
    ): ?WhatsappMessage {
        $to = $this->normalize($to);
        if ($to === null) {
            return null; // no recipient — nothing to send (e.g. guest without phone)
        }

        $language = (string) config('services.whatsapp.default_language', 'ar');

        $message = WhatsappMessage::create([
            'user_id' => $userId ?? $order?->user_id,
            'order_id' => $order?->id,
            'recipient' => $to,
            'template' => $template,
            'category' => $category,
            'purpose' => $purpose,
            'status' => 'queued',
            'payload' => ['language' => $language, 'params' => $params],
        ]);

        try {
            $wamId = $this->gateway->sendTemplate($to, $template, $language, $params);
            $message->update(['status' => 'sent', 'wam_id' => $wamId, 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $message->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 1000)]);
            Log::warning('WhatsApp send failed', ['template' => $template, 'to' => $to, 'error' => $e->getMessage()]);
        }

        return $message;
    }

    /**
     * Normalize a phone to E.164 digits (no '+'), as Meta expects. Returns null
     * for empty/garbage input.
     */
    private function normalize(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * @return list<string>
     */
    private function adminRecipients(): array
    {
        $raw = (string) config('services.whatsapp.admin_recipients', '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
