<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Services\CustomerMailer;
use App\Services\OrderConfirmationService;
use App\Services\Shipping\ShippingService;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\TableExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

/**
 * Back-office order management: list, inspect, and drive the order lifecycle
 * (confirm / mark-unavailable / ship / cancel). All state changes go through the
 * service layer — this controller only validates input and shapes props.
 */
class OrderController extends Controller
{
    public function __construct(
        protected OrderConfirmationService $confirmation,
        protected ShippingService $shipping,
        protected WhatsAppService $whatsapp,
        protected CustomerMailer $mailer,
    ) {}

    /** Whitelisted sort columns for the table/export. */
    private const SORTABLE = ['order_number', 'customer_name', 'status', 'payment_status', 'total', 'created_at'];

    /** Full field set for the export, in column order. */
    private const EXPORT_COLUMNS = [
        'order_number', 'customer_name', 'customer_email', 'customer_phone',
        'status', 'payment_status', 'payment_method', 'subtotal', 'discount_total',
        'shipping_fee', 'total', 'currency', 'tracking_number', 'carrier',
        'created_at', 'confirmed_at', 'delivered_at',
    ];

    public function index(Request $request)
    {
        $perPage = $this->perPage($request, 20);
        $orders = $this->filteredQuery($request)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'payment_method' => $order->payment_method?->value,
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
            'filters' => [
                'status' => $request->query('status'),
                'sort' => in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : null,
                'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
                'per_page' => $perPage,
            ],
            'statuses' => array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()),
            'counts' => $this->statusCounts(),
        ]);
    }

    /** Shared list query for the table and export: status filter + whitelisted sort. */
    private function filteredQuery(Request $request)
    {
        $status = $request->query('status');
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : null;
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return Order::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($sort, fn ($q) => $q->orderBy($sort, $direction), fn ($q) => $q->latest());
    }

    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request)
            ->get()
            ->map(fn (Order $o) => [
                'order_number' => $o->order_number,
                'customer_name' => $o->customer_name,
                'customer_email' => $o->customer_email,
                'customer_phone' => $o->customer_phone,
                'status' => $o->status->value,
                'payment_status' => $o->payment_status->value,
                'payment_method' => $o->payment_method?->value,
                'subtotal' => (float) $o->subtotal,
                'discount_total' => (float) $o->discount_total,
                'shipping_fee' => (float) $o->shipping_fee,
                'total' => (float) $o->total,
                'currency' => $o->currency,
                'tracking_number' => $o->tracking_number,
                'carrier' => $o->carrier,
                'created_at' => $o->created_at?->toDateTimeString(),
                'confirmed_at' => $o->confirmed_at?->toDateTimeString(),
                'delivered_at' => $o->delivered_at?->toDateTimeString(),
            ]);

        return TableExport::download((string) $request->query('format'), 'orders', self::EXPORT_COLUMNS, $rows);
    }

    public function show(Order $order)
    {
        return Inertia::render('admin/orders/show', $this->detailData($order));
    }

    /**
     * Live carrier options for the shipping picker, fetched on demand.
     *
     * A separate JSON endpoint rather than a prop on the show page, because the
     * picker is rendered by the shared OrderDetailView — which also runs inside
     * the in-list modal, where there is no Inertia page of its own to attach a
     * prop to. It sits alongside the existing `detail` endpoint that modal
     * already fetches, so both surfaces load quotes the same way.
     *
     * On demand and never eagerly: quoting crosses the network to OTO and pushes
     * the order there first, which is far too much to do on every page view.
     *
     * Always answers 200 with a usable shape. A quote can fail for reasons that
     * have nothing to do with this order (credentials, an outage, a destination
     * OTO won't serve), and the admin needs to SEE why rather than get a dead
     * spinner — so the error travels as data. Automatic shipping still works in
     * that case, since fulfill() re-quotes server-side.
     */
    public function shippingQuotes(Order $order): JsonResponse
    {
        try {
            $quoted = $this->shipping->quote($order);

            $options = collect($quoted)
                ->sortBy('price')
                ->values()
                ->map(fn ($option) => $option->toArray())
                ->all();

            return response()->json([
                'options' => $options,
                // Which row automatic would actually ship. Asked of the service
                // rather than assumed to be the first: the cheapest option is
                // not always the one automatic takes (it skips pickup points),
                // and a dialog that badges the wrong row is worse than one that
                // badges none.
                'auto_option_id' => ShippingService::preferredOption($quoted)?->id,
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['options' => [], 'auto_option_id' => null, 'error' => $e->getMessage()]);
        }
    }

    /** JSON detail for the in-list order modal (same payload as the show page). */
    public function detail(Order $order)
    {
        return response()->json($this->detailData($order));
    }

    /**
     * Full order payload + available lifecycle actions, shared by the show page
     * and the in-list modal.
     *
     * @return array<string, mixed>
     */
    private function detailData(Order $order): array
    {
        $order->load(['items', 'activities.user', 'confirmedBy', 'coupon']);

        return [
            'order' => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'shipping_address' => $order->shipping_address,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'payment_method' => $order->payment_method?->value,
                'subtotal' => (float) $order->subtotal,
                'discount_total' => (float) $order->discount_total,
                'shipping_fee' => (float) $order->shipping_fee,
                // What the carrier charged US, against the flat fee above. Null
                // for orders shipped before it was recorded, so it stays nullable
                // rather than being cast to a misleading 0.
                'shipping_cost' => $order->shipping_cost === null ? null : (float) $order->shipping_cost,
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'tracking_number' => $order->tracking_number,
                'carrier' => $order->carrier,
                'admin_notes' => $order->admin_notes,
                'confirmed_by' => $order->confirmedBy?->name,
                'confirmed_at' => $order->confirmed_at?->toDateTimeString(),
                'delivered_at' => $order->delivered_at?->toDateTimeString(),
                'created_at' => $order->created_at?->toDateTimeString(),
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->product_name_ar,
                    'sku' => $item->sku,
                    'unit_price' => (float) $item->unit_price,
                    'quantity' => $item->quantity,
                    'line_total' => (float) $item->line_total,
                ]),
                'activities' => $order->activities->sortByDesc('created_at')->values()->map(fn ($a) => [
                    'type' => $a->type,
                    'from_status' => $a->from_status,
                    'to_status' => $a->to_status,
                    'note' => $a->note,
                    // Carries the carrier / tracking number / cost for shipping
                    // entries. Previously omitted, which is why a shipment showed
                    // in the timeline as the bare word "tracking".
                    'meta' => $a->meta,
                    'user' => $a->user?->name,
                    'created_at' => $a->created_at?->toDateTimeString(),
                ]),
            ],
            'can' => [
                'confirm' => $order->status === OrderStatus::AwaitingConfirmation,
                'unavailable' => $order->status === OrderStatus::AwaitingConfirmation,
                'ship' => $order->status === OrderStatus::Confirmed && ! $order->tracking_number,
                // 🔴 This used to be `status === Confirmed`, which is the exact
                // complement of what cancelByCustomer() accepts — so the button
                // appeared only in the one state guaranteed to fail, and clicking
                // it always flashed "This order can no longer be cancelled."
                // Ask the enum rather than restating its rule here.
                'cancel' => $order->status->isCancellableByCustomer(),
                // Cancelling the SHIPMENT is a different operation from cancelling
                // the ORDER: it recalls the parcel and returns the order to
                // confirmed so it can be shipped again, and moves no money.
                // Excluded once delivered — there is nothing left to recall.
                'cancelShipment' => $order->tracking_number !== null && $order->status === OrderStatus::Shipped,
                // Recovery for a gateway hold that lapsed before we confirmed it.
                // Asks the model rather than restating the rule — the same predicate
                // the storefront pay route and the account list already use, so the
                // three cannot drift (the 2026-08-15 admin-cancel bug).
                // `customer_phone` is NOT NULL on orders, so there is no phone check
                // here — an unreachable guard reads as a real one to the next person.
                'sendPaymentLink' => $order->isAwaitingGatewayPayment(),
            ],
        ];
    }

    public function confirm(Order $order)
    {
        try {
            $this->confirmation->confirm($order, Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Best-effort notifications (never block the confirmation).
        $this->whatsapp->notifyOrderConfirmed($order);
        $this->mailer->orderConfirmed($order);
        if ($this->confirmation->issuedReward) {
            $this->whatsapp->notifyLoyaltyReward($order, $this->confirmation->issuedReward);
        }

        return back()->with('success', __('messages.admin.order_confirmed'));
    }

    public function markUnavailable(Request $request, Order $order)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->confirmation->markUnavailable($order, Auth::id(), $data['note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->whatsapp->notifyOrderUnavailable($order);

        return back()->with('success', __('messages.admin.order_unavailable'));
    }

    /**
     * Create the shipment. `delivery_option_id` is optional — omitted means the
     * cheapest available carrier, which is the default because the customer pays
     * a flat rate regardless, so the difference is entirely the store's margin.
     * Staff can override it (a faster carrier, or one that actually serves a
     * remote district) from the picker.
     *
     * The id is deliberately NOT validated against a fresh quote: that would
     * double the OTO calls on every shipment, and a stale id is self-correcting
     * — OTO rejects it and the admin sees the error and re-opens the picker.
     */
    public function ship(Request $request, Order $order)
    {
        $data = $request->validate([
            'delivery_option_id' => ['nullable', 'integer'],
        ]);

        try {
            $this->shipping->fulfill($order, $data['delivery_option_id'] ?? null, Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->whatsapp->notifyOrderShipped($order->refresh());
        $this->mailer->orderShipped($order);

        return back()->with('success', __('messages.admin.shipment_created'));
    }

    /**
     * Recall the shipment from the carrier and return the order to confirmed so
     * it can be shipped again (typically with a different carrier).
     *
     * ⚠️ This moves NO money, deliberately. The order is still live and will
     * still be delivered, so refunding the customer's shipping fee would be
     * wrong. Cancelling the ORDER is a separate action with its own refund path.
     */
    public function cancelShipment(Order $order)
    {
        try {
            $this->shipping->cancel($order, Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('messages.admin.shipment_cancelled'));
    }

    /**
     * Send the customer a signed link to finish paying an order whose gateway hold
     * lapsed before we confirmed it.
     *
     * 🔑 Staff-triggered rather than automatic, deliberately. The hold expired
     * because WE did not confirm in time, so the customer did nothing wrong — and
     * a canned "something went wrong" from the system that dropped the ball reads
     * badly. Volume should be near zero if the expiry alert is working, so the
     * human step costs almost nothing and lets staff apologise or attach a coupon.
     * The WORK is automatic: one click reopens the order and sends the link.
     */
    public function sendPaymentLink(Order $order)
    {
        if (! $order->isAwaitingGatewayPayment()) {
            return back()->with('error', __('messages.admin.payment_link_not_applicable'));
        }
        // Three days: long enough that a customer who reads the message the next
        // morning is not locked out, short enough that a forwarded link does not
        // stay live indefinitely.
        $url = URL::temporarySignedRoute('orders.resume', now()->addDays(3), ['order' => $order->order_number]);

        $sent = $this->whatsapp->sendPaymentLink($order, $url);

        OrderActivity::create([
            'order_id' => $order->id,
            'type' => 'payment_link_sent',
            'user_id' => Auth::id(),
            'meta' => ['channel' => 'whatsapp', 'queued' => $sent !== null],
        ]);

        return back()->with(
            $sent ? 'success' : 'error',
            $sent ? __('messages.admin.payment_link_sent') : __('messages.admin.payment_link_failed'),
        );
    }

    public function cancel(Order $order)
    {
        try {
            $this->confirmation->cancelByCustomer($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('messages.admin.order_cancelled'));
    }

    /**
     * Order counts per status for the index filter tabs.
     *
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        return Order::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();
    }
}
