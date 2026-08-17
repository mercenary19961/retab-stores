<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Notifications\OrderCancelledNotification;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CustomerMailer;
use App\Services\OrderConfirmationService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Tamara\TamaraService;
use App\Services\ReturnService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class CheckoutController
{
    /** GCC destinations we ship to. */
    private const GCC = ['SA', 'AE', 'KW', 'QA', 'BH', 'OM'];

    public function __construct(
        protected CartService $cart,
        protected CheckoutService $checkout,
        protected WhatsAppService $whatsapp,
        protected CustomerMailer $mailer,
    ) {}

    public function show(Request $request)
    {
        $summary = $this->cart->summary();

        if ($summary['count'] === 0) {
            return redirect()->route('cart.show');
        }

        return Inertia::render('shop/checkout', [
            'items' => $summary['items'],
            'subtotal' => $summary['subtotal'],
            // Effective fee: 0 during an automatic free-shipping window.
            'shippingFee' => $this->checkout->shippingFee(),
            'freeShipping' => $this->checkout->freeShippingActive(),
            // Pre-fill a coupon already applied on the cart page so the shopper
            // doesn't have to type it twice (the form still submits it, and
            // placeOrder re-validates it under lock — this is only convenience).
            'appliedCoupon' => $request->session()->get(CartController::COUPON_SESSION_KEY),
            'countries' => self::GCC,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'country' => ['required', 'in:'.implode(',', self::GCC)],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', 'in:card,tamara,bank_transfer'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
        ]);

        $cart = $this->cart->current();
        if ($cart->items()->count() === 0) {
            return redirect()->route('cart.show')->with('error', __('messages.cart.empty'));
        }

        try {
            $order = $this->checkout->placeOrder(
                $cart,
                ['name' => $data['customer_name'], 'email' => $data['customer_email'] ?? null, 'phone' => $data['customer_phone']],
                [
                    'country' => $data['country'],
                    'city' => $data['city'],
                    'district' => $data['district'] ?? null,
                    'street' => $data['street'] ?? null,
                    'building' => $data['building'] ?? null,
                    'phone' => $data['customer_phone'],
                ],
                $data['coupon_code'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->cart->clear($cart);
        /** @var Store $session — push() lives on Store, not the Session contract the docblock advertises */
        $session = $request->session();
        $session->push('placed_orders', $order->order_number);
        // The coupon is spent now (placeOrder recorded its redemption), so drop the
        // cart-page copy — otherwise it would silently reappear on the next order.
        $session->forget(CartController::COUPON_SESSION_KEY);

        // Alert staff that a new order needs attention (verify transfer / check stock)
        // across all three channels: WhatsApp + the in-panel notification bell.
        $this->whatsapp->notifyAdminsNewOrder($order);
        Notification::send(User::staff()->get(), new NewOrderNotification($order));

        // Record the CHOSEN method immediately, for all three. It used to be written
        // only on the bank-transfer branch (card/Tamara set it when the payment
        // landed), which left an unpaid card order with a null `payment_method` —
        // so nothing could tell which gateway to send the customer back to, and
        // `pay()` below would have no way to resume. Both dashboard counts that key
        // on this column also filter on `payment_status`, so an unpaid card/Tamara
        // order still can't drift into them.
        $order->update(['payment_method' => PaymentMethod::from($data['payment_method'])]);

        if (in_array($data['payment_method'], ['card', 'tamara'], true)) {
            try {
                $url = $data['payment_method'] === 'card'
                    ? app(PaymentService::class)->initiate($order)
                    : app(TamaraService::class)->initiate($order);

                // Full-page redirect out to the hosted gateway.
                return Inertia::location($url);
            } catch (\Throwable $e) {
                Log::error('Payment initiation failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);

                return redirect()->route('orders.show', $order->order_number)
                    ->with('error', __('messages.payment.init_failed'));
            }
        }

        // Bank transfer — no gateway; the order page shows the IBAN to transfer to.
        //
        // Email the transfer instructions NOW. This is the one payment method with
        // no gateway receipt of its own, and the IBAN + the order number the
        // customer must quote as the reference otherwise live on a single page
        // view that's gone the moment they close the tab.
        //
        // ⚠️ Must come AFTER `payment_method` is set above: the mail decides whether
        // to render the bank block from that column. Card/Tamara receipts are sent
        // on payment confirmation instead, so an abandoned gateway checkout never
        // produces a receipt (see PaymentService).
        $this->mailer->orderPlaced($order->refresh());

        return redirect()->route('orders.show', $order->order_number);
    }

    /**
     * Send the customer back to the hosted gateway for an order they never paid.
     *
     * 🔴 Without this an abandoned card/Tamara checkout was UNRECOVERABLE: the
     * order sat in `pending_payment` and `orders.payment_url` was stored but never
     * surfaced anywhere, so closing the gateway tab (or a declined card, or a
     * failed `initiate()`) left the customer on an order page with no way to pay
     * and no way back. Every one of those was a lost sale.
     */
    public function pay(Request $request, Order $order)
    {
        $this->assertOwns($request, $order);
        abort_unless($this->canPay($order), 403);

        // A FAILED attempt cannot be resumed on the same invoice — the gateway has
        // already closed it — so drop the stored session and let initiate() mint a
        // fresh one. Only `pending` (never completed, e.g. the tab was closed) is
        // safe to hand back as-is, which is what initiate() does when both fields
        // are still present.
        if ($order->payment_status === PaymentStatus::Failed) {
            $order->forceFill(['payment_url' => null, 'gateway_reference' => null])->save();
        }

        try {
            $url = $order->payment_method === PaymentMethod::Card
                ? app(PaymentService::class)->initiate($order)
                : app(TamaraService::class)->initiate($order);
        } catch (\Throwable $e) {
            Log::error('Payment resume failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);

            return back()->with('error', __('messages.payment.init_failed'));
        }

        return Inertia::location($url);
    }

    /**
     * Where a hosted gateway returns the customer after payment.
     *
     * 🔴 Neither gateway's return URL existed. Tamara sends the shopper to
     * `/checkout/result` and Moyasar to `/checkout/success`, and BOTH 404'd — so
     * a customer paid, got redirected, and landed on an error page. Worse, that
     * left the webhook as the ONLY thing able to advance the order, so a delayed
     * or misconfigured callback stranded a paid order at `pending_payment` with
     * nobody watching.
     *
     * 🔑 This is deliberately a SECOND path to the same outcome, not a
     * replacement. The webhook stays authoritative (it re-fetches and verifies
     * amount + currency); this just lets the customer's own return journey
     * confirm the order too, so a webhook problem degrades instead of stalling.
     * Both services are idempotent and row-locked, so the two racing is a no-op.
     */
    public function result(Request $request)
    {
        $order = $this->resolveReturnedOrder($request);

        if (! $order) {
            return redirect()->route('home')->with('error', __('messages.payment.result_unknown'));
        }

        // Best-effort by design: the webhook is the authority, so a failure here
        // must still land the customer on their order rather than a 500.
        try {
            $this->confirmReturnedPayment($order);
        } catch (\Throwable $e) {
            Log::warning('Gateway return confirmation failed', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }

        $order->refresh();

        // Cancelled or declined: the order page already renders the pay button
        // (canPay), so this only has to explain why they are looking at it.
        return redirect()->route('orders.show', $order->order_number)
            ->with($this->canPay($order) ? 'error' : 'success', $this->canPay($order)
                ? __('messages.payment.not_completed')
                : __('messages.payment.received'));
    }

    /**
     * Which order did this visitor just come back from paying?
     *
     * Preference order matters. Our OWN session record is checked first because
     * it cannot be influenced by the query string, and it survives the round
     * trip: a top-level GET redirect from the gateway carries a SameSite=Lax
     * cookie. The gateway reference is only a fallback for a lost session
     * (expired, cookies cleared, finished on another device).
     */
    private function resolveReturnedOrder(Request $request): ?Order
    {
        /** @var Store $session — push() lives on Store, not the Session contract */
        $session = $request->session();
        $placed = $session->get('placed_orders', []);

        if ($placed !== [] && $order = Order::where('order_number', end($placed))->first()) {
            return $order;
        }

        // ⚠️ Moyasar returns `?id=<PAYMENT id>` while our `gateway_reference`
        // holds the INVOICE id, so the payment id can't be matched directly —
        // confirmFromGateway resolves the order from the payment itself.
        // Tamara returns `?orderId=<its order id>`, which is what we stored.
        $order = ($paymentId = $request->query('id'))
            ? app(PaymentService::class)->confirmFromGateway((string) $paymentId)
            : Order::where('gateway_reference', (string) ($request->query('orderId') ?? $request->query('order_id') ?? ''))
                ->whereNotNull('gateway_reference')
                ->first();

        // Grant the confirmation page to a visitor who arrived holding a valid
        // gateway-side reference. ⚠️ Residual risk accepted knowingly: those
        // references are server-generated UUIDs known only to us, the gateway
        // and this customer, so reaching here with someone else's requires
        // guessing one. The alternative is stranding a paying customer who lost
        // their session, which is the exact failure this method exists to fix.
        if ($order) {
            $session->push('placed_orders', $order->order_number);
        }

        return $order;
    }

    /**
     * Ask the gateway what really happened. Skipped once a webhook has already
     * settled the order, so the common case costs no outbound call.
     */
    private function confirmReturnedPayment(Order $order): void
    {
        if (in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::Authorized], true)) {
            return;
        }

        if ($order->payment_method === PaymentMethod::Tamara && $order->gateway_reference) {
            app(TamaraService::class)->confirm($order->gateway_reference);

            return;
        }

        if ($order->payment_method === PaymentMethod::Card) {
            // reconcile() reads the INVOICE and confirms whichever payment on it
            // succeeded, so it needs no id from the query string.
            app(PaymentService::class)->reconcile($order);
        }
    }

    /**
     * Customer cancels their own order, allowed only before an admin confirms.
     *
     * An explicit requirement of the client brief that had no storefront route
     * at all: `OrderConfirmationService::cancelByCustomer()` is literally named
     * for this, but the only thing reaching it was the admin panel.
     *
     * 🔑 The window is asked of the ENUM, never restated here. Restating it is
     * exactly how the admin panel ended up offering a Cancel button in the one
     * state where the service was guaranteed to reject it (2026-08-15).
     * `cancelByCustomer()` releases the money as it goes: a Tamara hold is
     * voided, a captured card is refunded, bank transfer has nothing to release.
     */
    public function cancel(Request $request, Order $order)
    {
        $this->assertOwns($request, $order);

        try {
            app(OrderConfirmationService::class)->cancelByCustomer($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Staff have to find out: someone may already be picking stock for this.
        // The bell writes synchronously so it works with no queue and no Meta
        // credentials; the WhatsApp fan-out is the extra, not the mechanism.
        Notification::send(User::staff()->get(), new OrderCancelledNotification($order));
        $this->whatsapp->notifyAdminsOrderCancelled($order);

        return back()->with('success', __('messages.orders.cancelled'));
    }

    /**
     * Is there a gateway payment still outstanding on this order?
     *
     * Delegates to the model so this page, the account order list and the pay
     * route can never disagree about it.
     */
    private function canPay(Order $order): bool
    {
        return $order->isAwaitingGatewayPayment();
    }

    /** Guests are authorised by the session; signed-in customers by ownership. */
    private function assertOwns(Request $request, Order $order): void
    {
        $placed = $request->session()->get('placed_orders', []);
        abort_unless(
            in_array($order->order_number, $placed, true) || ($request->user() && $order->user_id === $request->user()->id),
            403,
        );
    }

    public function confirmation(Request $request, Order $order)
    {
        $this->assertOwns($request, $order);

        $bank = null;
        // 🔴 `payment_status` stays `pending` on a cancelled bank transfer (there
        // is nothing to release), so without the status check this page kept
        // showing the IBAN and telling the customer to transfer money for an
        // order that no longer exists.
        if ($order->payment_method === PaymentMethod::BankTransfer
            && $order->payment_status->value === 'pending'
            && $order->status !== OrderStatus::Cancelled) {
            $bank = [
                'bank_name' => Setting::get('bank_name'),
                'beneficiary' => Setting::get('bank_beneficiary'),
                'account' => Setting::get('bank_account'),
                'iban' => Setting::get('bank_iban'),
            ];
        }

        $latestReturn = $order->returns()->latest()->first();

        return Inertia::render('shop/order-confirmation', [
            'order' => [
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'payment_method' => $order->payment_method?->value,
                'total' => (float) $order->total,
            ],
            'bank' => $bank,
            'canPay' => $this->canPay($order),
            // Asked of the enum, so the button and the service can never disagree.
            'canCancel' => $order->status->isCancellableByCustomer(),
            // Defect/damage returns: offer the form only to the signed-in owner
            // while the order is delivered + inside the 3-day window.
            'canReturn' => $request->user()
                && $order->user_id === $request->user()->id
                && app(ReturnService::class)->canRequest($order),
            'orderReturn' => $latestReturn ? ['status' => $latestReturn->status->value] : null,
        ]);
    }
}
