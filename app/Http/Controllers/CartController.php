<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Support\ProductCards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController
{
    /** Session key holding the shopper's applied coupon code (carried to checkout). */
    public const COUPON_SESSION_KEY = 'cart_coupon';

    public function __construct(
        protected CartService $cart,
        protected CheckoutService $checkout,
    ) {}

    public function show(Request $request): Response
    {
        $summary = $this->cart->summary();
        $subtotal = (float) $summary['subtotal'];

        [$coupon, $discount, $couponError] = $this->appliedCoupon($request, $subtotal);

        // A free-shipping coupon waives the fee rather than discounting the goods
        // (Coupon::discountFor returns 0 for that type) — mirrors placeOrder.
        $shippingFee = $this->checkout->shippingFee();
        if ($coupon?->wavesShipping()) {
            $shippingFee = 0.0;
        }

        return Inertia::render('shop/cart', [
            ...$summary,

            // Money is broken out so the cart shows the same arithmetic the order
            // will use, instead of the old vague "shipping is added at checkout".
            'shippingFee' => $shippingFee,
            'freeShipping' => $this->checkout->freeShippingActive(),
            'discount' => $discount,
            'total' => round($subtotal - $discount + $shippingFee, 2),

            'coupon' => $coupon ? [
                'code' => $coupon->code,
                'waives_shipping' => $coupon->wavesShipping(),
            ] : null,
            // Set when a previously-applied coupon has since become unusable (cart
            // fell below its minimum, it expired, it hit its cap…). Surfaced inline
            // so the shopper isn't silently charged more than they expected.
            'couponError' => $couponError,

            // Empty state only — an empty cart is otherwise a dead end.
            'bestSellers' => $summary['count'] === 0 ? ProductCards::bestSellers(4) : [],
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'option_id' => ['nullable', 'integer', 'exists:product_options,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::where('is_active', true)->findOrFail($data['product_id']);

        // Resolve the chosen option, if any. It must belong to this product and be
        // active. No option means the ORIGINAL product — always a valid choice,
        // even when sizes exist (it is the default the page opens on).
        $option = null;
        if (! empty($data['option_id'])) {
            $option = $product->activeOptions()->find($data['option_id']);
            abort_unless($option !== null, 422, __('messages.cart.option_unavailable'));
        }

        $this->cart->add($product, $data['quantity'] ?? 1, $option);

        return back()->with('success', __('messages.cart.added'));
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->ensureOwned($item);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);
        $this->cart->updateQuantity($item, $data['quantity']);

        return back();
    }

    public function remove(CartItem $item): RedirectResponse
    {
        $this->ensureOwned($item);
        $this->cart->remove($item);

        return back();
    }

    /**
     * Apply a coupon from the cart page. This only PREVIEWS it — nothing is
     * redeemed until the order is placed, and placeOrder re-validates under a row
     * lock, so an early preview can never over-redeem a usage-capped coupon.
     */
    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:60']]);
        $code = trim($data['code']);

        try {
            $this->checkout->previewCoupon($code, (float) $this->cart->summary()['subtotal'], $request->user()?->id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $request->session()->put(self::COUPON_SESSION_KEY, $code);

        return back()->with('success', __('messages.cart.coupon_applied'));
    }

    public function removeCoupon(Request $request): RedirectResponse
    {
        $request->session()->forget(self::COUPON_SESSION_KEY);

        return back()->with('success', __('messages.cart.coupon_removed'));
    }

    /**
     * Re-validate the session coupon on every render. The cart is mutable, so a
     * coupon that qualified when applied can stop qualifying (subtotal fell under
     * its minimum, it expired meanwhile). Re-checking here — through the same
     * validator checkout uses — is what stops the cart displaying a discount the
     * order would then refuse.
     *
     * @return array{0: Coupon|null, 1: float, 2: string|null}
     */
    private function appliedCoupon(Request $request, float $subtotal): array
    {
        $code = $request->session()->get(self::COUPON_SESSION_KEY);

        if (! $code || $subtotal <= 0) {
            return [null, 0.0, null];
        }

        try {
            [$coupon, $discount] = $this->checkout->previewCoupon($code, $subtotal, $request->user()?->id);
        } catch (\RuntimeException $e) {
            // Drop it rather than leave a stale code to resurface at checkout.
            $request->session()->forget(self::COUPON_SESSION_KEY);

            return [null, 0.0, $e->getMessage()];
        }

        return [$coupon, $discount, null];
    }

    private function ensureOwned(CartItem $item): void
    {
        abort_unless($item->cart_id === $this->cart->current()->id, 403);
    }
}
