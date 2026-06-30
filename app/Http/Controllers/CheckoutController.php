<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Tamara\TamaraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CheckoutController
{
    /** GCC destinations we ship to. */
    private const GCC = ['SA', 'AE', 'KW', 'QA', 'BH', 'OM'];

    public function __construct(
        protected CartService $cart,
        protected CheckoutService $checkout,
        protected \App\Services\WhatsApp\WhatsAppService $whatsapp,
    ) {}

    public function show()
    {
        $summary = $this->cart->summary();

        if ($summary['count'] === 0) {
            return redirect()->route('cart.show');
        }

        return Inertia::render('shop/checkout', [
            'items' => $summary['items'],
            'subtotal' => $summary['subtotal'],
            'shippingFee' => (float) Setting::get(CheckoutService::SHIPPING_FEE_KEY, 0),
            'countries' => self::GCC,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'country' => ['required', 'in:' . implode(',', self::GCC)],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', 'in:card,tamara,bank_transfer'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
        ]);

        $cart = $this->cart->current();
        if ($cart->items()->count() === 0) {
            return redirect()->route('cart.show')->with('error', 'سلتك فارغة.');
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
        $request->session()->push('placed_orders', $order->order_number);

        // Alert staff that a new order needs attention (verify transfer / check stock).
        $this->whatsapp->notifyAdminsNewOrder($order);

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
                    ->with('error', 'تعذّر بدء الدفع الإلكتروني. يمكنك إتمام الدفع عبر التحويل البنكي.');
            }
        }

        // Bank transfer — no gateway; the order page shows the IBAN to transfer to.
        $order->update(['payment_method' => PaymentMethod::BankTransfer]);

        return redirect()->route('orders.show', $order->order_number);
    }

    public function confirmation(Request $request, Order $order)
    {
        $placed = $request->session()->get('placed_orders', []);
        abort_unless(
            in_array($order->order_number, $placed, true) || ($request->user() && $order->user_id === $request->user()->id),
            403,
        );

        $bank = null;
        if ($order->payment_method === PaymentMethod::BankTransfer && $order->payment_status->value === 'pending') {
            $bank = [
                'bank_name' => Setting::get('bank_name'),
                'beneficiary' => Setting::get('bank_beneficiary'),
                'account' => Setting::get('bank_account'),
                'iban' => Setting::get('bank_iban'),
            ];
        }

        return Inertia::render('shop/order-confirmation', [
            'order' => [
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'payment_method' => $order->payment_method?->value,
                'total' => (float) $order->total,
            ],
            'bank' => $bank,
        ]);
    }
}
