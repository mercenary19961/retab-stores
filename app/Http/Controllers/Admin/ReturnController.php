<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
use App\Services\ReturnService;
use App\Support\Media;
use App\Support\TableExport;
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

    /** Whitelisted sort columns. order_number/customer_name sort via the joined order. */
    private const SORTABLE = ['id', 'order_number', 'customer_name', 'status', 'created_at'];

    /** Full field set for the export, in column order. */
    private const EXPORT_COLUMNS = [
        'id', 'order_number', 'customer', 'status', 'resolution', 'refund_amount',
        'refund_shipping', 'reason', 'admin_notes', 'created_at', 'resolved_at',
    ];

    public function index(Request $request)
    {
        return Inertia::render('admin/returns/index', [
            'returns' => $this->filteredQuery($request)->paginate(20)->withQueryString()->through(fn (OrderReturn $r) => [
                'id' => $r->id,
                'order_number' => $r->order?->order_number,
                'customer' => $r->order?->customer_name ?? $r->user?->name,
                'status' => $r->status->value,
                'reason' => str($r->reason)->limit(80)->toString(),
                'created_at' => $r->created_at?->toDateTimeString(),
            ]),
            'filters' => [
                'status' => $request->query('status'),
                'sort' => in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : null,
                'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
            ],
            'statuses' => array_column(ReturnStatus::cases(), 'value'),
            'counts' => OrderReturn::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    /** Shared list query for the table and export: status filter + whitelisted sort. */
    private function filteredQuery(Request $request)
    {
        $status = $request->query('status');
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : null;
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        // status qualified so it stays unambiguous once the order join is added.
        $query = OrderReturn::with('order:id,order_number,customer_name,total', 'user:id,name')
            ->when($status, fn ($q) => $q->where('order_returns.status', $status));

        if (in_array($sort, ['order_number', 'customer_name'], true)) {
            $query->leftJoin('orders', 'orders.id', '=', 'order_returns.order_id')
                ->orderBy("orders.{$sort}", $direction)
                ->select('order_returns.*');
        } elseif ($sort) {
            $query->orderBy("order_returns.{$sort}", $direction);
        } else {
            $query->latest();
        }

        return $query;
    }

    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request)->get()->map(fn (OrderReturn $r) => [
            'id' => $r->id,
            'order_number' => $r->order?->order_number,
            'customer' => $r->order?->customer_name ?? $r->user?->name,
            'status' => $r->status->value,
            'resolution' => $r->resolution,
            'refund_amount' => $r->refund_amount !== null ? (float) $r->refund_amount : null,
            'refund_shipping' => (int) $r->refund_shipping,
            'reason' => $r->reason,
            'admin_notes' => $r->admin_notes,
            'created_at' => $r->created_at?->toDateTimeString(),
            'resolved_at' => $r->resolved_at?->toDateTimeString(),
        ]);

        return TableExport::download((string) $request->query('format'), 'returns', self::EXPORT_COLUMNS, $rows);
    }

    public function show(OrderReturn $orderReturn)
    {
        return Inertia::render('admin/returns/show', $this->detailData($orderReturn));
    }

    /** JSON detail for the in-list return modal (same payload as the show page). */
    public function detail(OrderReturn $orderReturn)
    {
        return response()->json($this->detailData($orderReturn));
    }

    /**
     * Return + order summary + both refund previews, shared by the show page and
     * the in-list modal.
     *
     * @return array<string, mixed>
     */
    private function detailData(OrderReturn $orderReturn): array
    {
        $orderReturn->load('order.items', 'items.orderItem', 'user:id,name', 'resolvedBy:id,name');
        $order = $orderReturn->order;

        return [
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
        ];
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
