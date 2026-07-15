<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderConfirmationService;
use App\Services\Shipping\ShippingService;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\TableExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $orders = $this->filteredQuery($request)
            ->paginate(20)
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
        $order->load(['items', 'activities.user', 'confirmedBy', 'coupon']);

        return Inertia::render('admin/orders/show', [
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
                    'user' => $a->user?->name,
                    'created_at' => $a->created_at?->toDateTimeString(),
                ]),
            ],
            'can' => [
                'confirm' => $order->status === OrderStatus::AwaitingConfirmation,
                'unavailable' => $order->status === OrderStatus::AwaitingConfirmation,
                'ship' => $order->status === OrderStatus::Confirmed && ! $order->tracking_number,
                'cancel' => in_array($order->status, [OrderStatus::Confirmed], true),
            ],
        ]);
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

    public function ship(Order $order)
    {
        try {
            $this->shipping->fulfill($order, null, Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->whatsapp->notifyOrderShipped($order->refresh());

        return back()->with('success', __('messages.admin.shipment_created'));
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
