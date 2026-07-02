<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
use App\Services\ReturnService;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Back-office review of defect/damage returns: inspect the photos, then
 * approve/reject, and resolve approved ones by exchange or refund (gateway
 * refunds are routed by ReturnService; shipping refunded only when damaged).
 */
class ReturnController extends Controller
{
    public function __construct(
        protected ReturnService $returns,
    ) {}

    public function index(Request $request)
    {
        $status = $request->query('status');

        $query = OrderReturn::with('order:id,order_number,customer_name,total', 'user:id,name')
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return Inertia::render('admin/returns/index', [
            'returns' => $query->paginate(20)->withQueryString()->through(fn (OrderReturn $r) => [
                'id' => $r->id,
                'order_number' => $r->order?->order_number,
                'customer' => $r->order?->customer_name ?? $r->user?->name,
                'status' => $r->status->value,
                'reason' => str($r->reason)->limit(80)->toString(),
                'created_at' => $r->created_at?->toDateTimeString(),
            ]),
            'filters' => ['status' => $status],
            'statuses' => array_column(ReturnStatus::cases(), 'value'),
            'counts' => OrderReturn::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(OrderReturn $orderReturn)
    {
        $orderReturn->load('order.items', 'items.orderItem', 'user:id,name', 'resolvedBy:id,name');
        $order = $orderReturn->order;

        return Inertia::render('admin/returns/show', [
            'orderReturn' => [
                'id' => $orderReturn->id,
                'status' => $orderReturn->status->value,
                'reason' => $orderReturn->reason,
                'photos' => collect($orderReturn->photos ?? [])->map(fn ($p) => Media::url($p))->filter()->values(),
                'resolution' => $orderReturn->resolution,
                'refund_amount' => $orderReturn->refund_amount !== null ? (float) $orderReturn->refund_amount : null,
                'refund_shipping' => (bool) $orderReturn->refund_shipping,
                'admin_notes' => $orderReturn->admin_notes,
                'resolved_at' => $orderReturn->resolved_at?->toDateTimeString(),
                'resolved_by' => $orderReturn->resolvedBy?->name,
                'created_at' => $orderReturn->created_at?->toDateTimeString(),
                'items' => $orderReturn->items->map(fn ($item) => [
                    'name_ar' => $item->orderItem?->product_name_ar,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) ($item->orderItem?->unit_price ?? 0),
                ])->values(),
            ],
            'order' => [
                'order_number' => $order?->order_number,
                'customer_name' => $order?->customer_name,
                'customer_phone' => $order?->customer_phone,
                'payment_method' => $order?->payment_method?->value,
                'total' => (float) ($order?->total ?? 0),
                'shipping_fee' => (float) ($order?->shipping_fee ?? 0),
                'delivered_at' => $order?->delivered_at?->toDateTimeString(),
            ],
            // Preview both refund amounts so the admin sees the effect of the toggle.
            'refundPreview' => [
                'items_only' => $this->returns->refundAmount($orderReturn, false),
                'with_shipping' => $this->returns->refundAmount($orderReturn, true),
            ],
        ]);
    }

    public function approve(Request $request, OrderReturn $orderReturn)
    {
        return $this->act(fn () => $this->returns->approve($orderReturn, Auth::id(), $request->input('notes')), 'return_approved');
    }

    public function reject(Request $request, OrderReturn $orderReturn)
    {
        return $this->act(fn () => $this->returns->reject($orderReturn, Auth::id(), $request->input('notes')), 'return_rejected');
    }

    public function exchange(Request $request, OrderReturn $orderReturn)
    {
        return $this->act(fn () => $this->returns->resolveExchange($orderReturn, Auth::id(), $request->input('notes')), 'return_exchanged');
    }

    public function refund(Request $request, OrderReturn $orderReturn)
    {
        $data = $request->validate([
            'refund_shipping' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->act(
            fn () => $this->returns->resolveRefund($orderReturn, Auth::id(), $data['refund_shipping'], $data['notes'] ?? null),
            'return_refunded',
        );
    }

    private function act(\Closure $action, string $successKey)
    {
        try {
            $action();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __("messages.admin.{$successKey}"));
    }
}
