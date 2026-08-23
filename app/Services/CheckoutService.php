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
                // Snapshot the checkout language: customer emails are queued, so the
                // worker's locale (AR) would otherwise decide what an EN shopper reads.
                'locale' => app()->getLocale(),
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

            // The chosen option (if any) must still be sellable and priced from
            // the option; only a plain product uses its own effective price.
            $option = $item->option;
            if ($option && (! $option->is_active || $option->product_id !== $product->id)) {
                throw new \RuntimeException('A product in your cart is no longer available.');
            }

            $unitPrice = $option ? $product->optionEffectivePrice($option) : $product->effectivePrice();
            $lineTotal = round((float) $unitPrice * $item->quantity, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'product_id' => $product->id,
                'product_option_id' => $option?->id,
                'product_name_ar' => $product->name_ar,
                'product_name_en' => $product->name_en,
                'option_label_ar' => $option?->label_ar,
                'option_label_en' => $option?->label_en,
                'sku' => $product->sku,
                'smacc_sku' => $option?->smacc_sku ?? $product->smacc_sku,
                'unit_price' => $unitPrice,
                'quantity' => $item->quantity,
                // Snapshot how many base units this line consumes, for stock.
                'stock_units' => $option?->stock_units ?? 1,
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

        // Lock the row for the transaction so the validity check and the
        // used_count increment can't interleave with a concurrent checkout and
        // over-redeem a usage-capped coupon. (placeOrder wraps this in a txn.)
        $coupon = Coupon::where('code', $couponCode)->lockForUpdate()->first();

        $this->assertCouponUsable($coupon, $subtotal, $userId);

        return [$coupon, $coupon->discountFor($subtotal)];
    }

    /**
     * Validate a coupon WITHOUT redeeming or locking it, for the cart page's
     * "apply coupon" preview.
     *
     * 🔑 Deliberately shares `assertCouponUsable()` with the real checkout path
     * above rather than re-implementing the rules. A second copy of this logic is
     * exactly how a cart ends up promising a discount that checkout then refuses —
     * the two can now only ever agree.
     *
     * No `lockForUpdate` here on purpose: this is a read-only preview, and taking
     * a row lock outside a transaction on every keystroke-ish request would be
     * pointless contention. The authoritative check still happens under lock in
     * placeOrder, so a coupon exhausted between preview and checkout is caught
     * there — the preview is a courtesy, never the gate.
     *
     * @return array{0: Coupon, 1: float} the coupon and its discount
     *
     * @throws \RuntimeException with a localized, user-facing message
     */
    public function previewCoupon(string $couponCode, float $subtotal, ?int $userId): array
    {
        $coupon = Coupon::where('code', $couponCode)->first();

        $this->assertCouponUsable($coupon, $subtotal, $userId);

        return [$coupon, $coupon->discountFor($subtotal)];
    }

    /**
     * The single source of truth for "may this customer use this coupon on this
     * subtotal?". Messages are localized because they surface straight to the
     * shopper as a flash / inline error.
     *
     * @phpstan-assert !null $coupon
     *
     * @throws \RuntimeException
     */
    private function assertCouponUsable(?Coupon $coupon, float $subtotal, ?int $userId): void
    {
        if (! $coupon || ! $coupon->isValid($subtotal)) {
            throw new \RuntimeException(__('messages.checkout.coupon_invalid'));
        }

        // A user-bound coupon (e.g. a loyalty reward) is only valid for its owner.
        if ($coupon->user_id !== null && $coupon->user_id !== $userId) {
            throw new \RuntimeException(__('messages.checkout.coupon_not_yours'));
        }

        // Per-user usage cap (only enforceable for signed-in customers).
        if ($coupon->per_user_limit !== null && $userId !== null
            && $coupon->redemptions()->where('user_id', $userId)->count() >= $coupon->per_user_limit) {
            throw new \RuntimeException(__('messages.checkout.coupon_used_up'));
        }
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'RTB-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
