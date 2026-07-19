<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a cart into a pending order. Snapshots line items, computes
 * subtotal + flat GCC shipping + coupon discount, and creates the order in
 * `pending_payment`. Stock is NOT deducted here — that happens at admin
 * confirmation, because website stock is advisory until the SMACC sync.
 */
class CheckoutService
{
    /** Settings key for the single flat GCC shipping fee. */
    public const SHIPPING_FEE_KEY = 'shipping_flat_fee';

    /** Store-wide automatic free-shipping promotion (set from the Discounts page). */
    public const FREE_SHIPPING_ACTIVE_KEY = 'free_shipping_active';

    public const FREE_SHIPPING_STARTS_KEY = 'free_shipping_starts_at';

    public const FREE_SHIPPING_ENDS_KEY = 'free_shipping_ends_at';

    /** Whether the automatic free-shipping promotion is on AND inside its window. */
    public function freeShippingActive(): bool
    {
        if (Setting::get(self::FREE_SHIPPING_ACTIVE_KEY) !== '1') {
            return false;
        }

        $starts = Setting::get(self::FREE_SHIPPING_STARTS_KEY);
        $ends = Setting::get(self::FREE_SHIPPING_ENDS_KEY);
        if (filled($starts) && Carbon::parse($starts)->isFuture()) {
            return false;
        }
        if (filled($ends) && Carbon::parse($ends)->isPast()) {
            return false;
        }

        return true;
    }

    /** The flat shipping fee the customer actually pays (0 during a free-shipping window). */
    public function shippingFee(): float
    {
        return $this->freeShippingActive() ? 0.0 : (float) Setting::get(self::SHIPPING_FEE_KEY, 0);
    }

    /**
     * @param  array{name?:string,email?:string,phone?:string}  $customer
     * @param  array<string,mixed>  $shippingAddress  GCC-format address
     */
    public function placeOrder(Cart $cart, array $customer, array $shippingAddress, ?string $couponCode = null): Order
    {
        $cart->loadMissing('items.product');

        if ($cart->items->isEmpty()) {
            throw new \RuntimeException('Cart is empty.');
        }

        return DB::transaction(function () use ($cart, $customer, $shippingAddress, $couponCode) {
            [$subtotal, $lines] = $this->buildLines($cart);

            [$coupon, $discount] = $this->resolveCoupon($couponCode, $subtotal, $cart->user_id);

            // Effective fee already accounts for an automatic free-shipping window;
            // a free-shipping coupon waives it too.
            $shippingFee = $this->shippingFee();
            if ($coupon && $coupon->wavesShipping()) {
                $shippingFee = 0.0;
            }
            $total = round($subtotal - $discount + $shippingFee, 2);

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $cart->user_id,
                'customer_name' => $customer['name'] ?? '',
                'customer_email' => $customer['email'] ?? null,
                'customer_phone' => $customer['phone'] ?? '',
                'shipping_address' => $shippingAddress,
                'status' => OrderStatus::PendingPayment,
                'payment_status' => PaymentStatus::Pending,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'shipping_fee' => $shippingFee,
                'total' => $total,
                'currency' => 'SAR',
                'coupon_id' => $coupon?->id,
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            if ($coupon) {
                // Reserve the coupon use now; an abandoned-order cleanup sweeper
                // (mirroring the payment reconciliation) can release it later.
                CouponRedemption::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $cart->user_id,
                    'order_id' => $order->id,
                    'discount_amount' => $discount,
                    'redeemed_at' => now(),
                ]);
                $coupon->increment('used_count');
            }

            return $order;
        });
    }

    /**
     * @return array{0: float, 1: array<int, array<string, mixed>>}
     */
    private function buildLines(Cart $cart): array
    {
        $subtotal = 0.0;
        $lines = [];

        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product || ! $product->is_active) {
                throw new \RuntimeException('A product in your cart is no longer available.');
            }

            $unitPrice = $product->effectivePrice();
            $lineTotal = round($unitPrice * $item->quantity, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'product_id' => $product->id,
                'product_name_ar' => $product->name_ar,
                'product_name_en' => $product->name_en,
                'sku' => $product->sku,
                'smacc_sku' => $product->smacc_sku,
                'unit_price' => $unitPrice,
                'quantity' => $item->quantity,
                'line_total' => $lineTotal,
            ];
        }

        return [round($subtotal, 2), $lines];
    }

    /**
     * @return array{0: ?Coupon, 1: float}
     */
    private function resolveCoupon(?string $couponCode, float $subtotal, ?int $userId): array
    {
        if (! $couponCode) {
            return [null, 0.0];
        }

        $coupon = Coupon::where('code', $couponCode)->first();
        if (! $coupon || ! $coupon->isValid($subtotal)) {
            throw new \RuntimeException('Invalid or expired coupon.');
        }

        // A user-bound coupon (e.g. a loyalty reward) is only valid for its owner.
        if ($coupon->user_id !== null && $coupon->user_id !== $userId) {
            throw new \RuntimeException('This coupon is not available for your account.');
        }

        // Per-user usage cap (only enforceable for signed-in customers).
        if ($coupon->per_user_limit !== null && $userId !== null
            && $coupon->redemptions()->where('user_id', $userId)->count() >= $coupon->per_user_limit) {
            throw new \RuntimeException('You have already used this coupon the maximum number of times.');
        }

        return [$coupon, $coupon->discountFor($subtotal)];
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'RTB-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
