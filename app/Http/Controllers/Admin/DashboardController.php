<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\DemandEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Smacc\SmaccImportService;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Admin landing page. Comprehensive at-a-glance overview: revenue KPIs + 30-day
 * trend, an action queue of what needs doing, inventory health, sales/demand
 * insights, and customer/loyalty counts. Read-only.
 *
 * Revenue is recognised on captured payments (payment_status = paid) and bucketed
 * by order date; most orders (cards) pay at checkout, so date ≈ paid date.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $now = now();

        // Paid revenue / order count within a window (BackedEnum binds by value).
        $paidRevenue = fn (Carbon $from, Carbon $to): float => (float) Order::where('payment_status', PaymentStatus::Paid)
            ->whereBetween('created_at', [$from, $to])->sum('total');
        $paidCount = fn (Carbon $from, Carbon $to): int => Order::where('payment_status', PaymentStatus::Paid)
            ->whereBetween('created_at', [$from, $to])->count();

        return Inertia::render('admin/dashboard', [
            'kpis' => [
                'currency' => 'SAR',
                'revenue30' => $paidRevenue($now->copy()->subDays(30), $now),
                'revenuePrev30' => $paidRevenue($now->copy()->subDays(60), $now->copy()->subDays(30)),
                'orders30' => $paidCount($now->copy()->subDays(30), $now),
                'ordersPrev30' => $paidCount($now->copy()->subDays(60), $now->copy()->subDays(30)),
                'revenueToday' => $paidRevenue($now->copy()->startOfDay(), $now),
                'revenueYesterday' => $paidRevenue($now->copy()->subDay()->startOfDay(), $now->copy()->startOfDay()),
            ],
            'trend' => $this->dailyRevenue(),
            'tasks' => $this->tasks(),
            'inventory' => $this->inventory(),
            'insights' => [
                'topProducts' => $this->topProducts(),
                'demand' => $this->demand(),
            ],
            'customers' => [
                'total' => User::where('role', 'customer')->count(),
                'new30' => User::where('role', 'customer')->where('created_at', '>=', $now->copy()->subDays(30))->count(),
                'whatsappAudience' => User::where('role', 'customer')->where('whatsapp_opt_in', true)->count(),
                'nearReward' => User::where('role', 'customer')->where('confirmed_purchases_count', 4)->count(),
            ],
            'recentOrders' => $this->recentOrders(),
        ]);
    }

    /**
     * Paid revenue + order count per day for the last 30 days, gaps filled with 0.
     *
     * @return list<array{date:string, revenue:float, orders:int}>
     */
    private function dailyRevenue(): array
    {
        $daily = Order::where('payment_status', PaymentStatus::Paid)
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as d, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        return collect(range(0, 29))->map(function (int $i) use ($daily) {
            $date = now()->subDays(29 - $i)->toDateString();
            $row = $daily->get($date);

            return [
                'date' => $date,
                'revenue' => (float) ($row->revenue ?? 0),
                'orders' => (int) ($row->orders ?? 0),
            ];
        })->all();
    }

    /**
     * The action queue: things that need a human decision, with counts + links.
     * `urgent` marks time-sensitive items (money on hold / expiring auths).
     *
     * @return list<array{key:string, count:int, href:string, urgent:bool}>
     */
    private function tasks(): array
    {
        return [
            [
                'key' => 'awaitingConfirmation',
                'count' => Order::where('status', OrderStatus::AwaitingConfirmation)->count(),
                'href' => '/admin/orders?status=awaiting_confirmation',
                'urgent' => false,
            ],
            [
                'key' => 'bankTransfers',
                'count' => Order::where('payment_method', PaymentMethod::BankTransfer)
                    ->where('payment_status', PaymentStatus::Pending)
                    ->where('status', OrderStatus::PendingPayment)->count(),
                'href' => '/admin/orders?status=pending_payment',
                'urgent' => true,
            ],
            [
                'key' => 'returnsToReview',
                'count' => OrderReturn::where('status', ReturnStatus::Requested)->count(),
                'href' => '/admin/returns?status=requested',
                'urgent' => false,
            ],
            [
                'key' => 'readyToShip',
                'count' => Order::where('status', OrderStatus::Confirmed)->count(),
                'href' => '/admin/orders?status=confirmed',
                'urgent' => false,
            ],
            [
                // Hidden draft products (imported incomplete) waiting to be finished.
                'key' => 'draftsToComplete',
                'count' => Product::where('is_active', false)->count(),
                'href' => '/admin/products?status=draft',
                'urgent' => false,
            ],
            [
                // Tamara authorisations expire (~48h); flag any held over 24h.
                'key' => 'tamaraExpiring',
                'count' => Order::where('payment_method', PaymentMethod::Tamara)
                    ->where('payment_status', PaymentStatus::Authorized)
                    ->where('status', OrderStatus::AwaitingConfirmation)
                    ->where('created_at', '<=', now()->subHours(24))->count(),
                'href' => '/admin/orders?status=awaiting_confirmation',
                'urgent' => true,
            ],
        ];
    }

    /**
     * Inventory health: SMACC sync freshness, out-of-stock + low-stock counts,
     * and the lowest-stock active products (mirrors the Inventory page's rule).
     *
     * @return array<string, mixed>
     */
    private function inventory(): array
    {
        $lowStockList = Product::where('is_active', true)
            ->whereRaw('stock <= COALESCE(low_stock_threshold, ?)', [5])
            ->orderBy('stock')
            ->limit(8)
            ->get(['id', 'name_ar', 'name_en', 'sku', 'stock'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name_ar' => $p->name_ar,
                'name_en' => $p->name_en,
                'sku' => $p->sku,
                'stock' => $p->stock,
            ])->all();

        return [
            'lastSynced' => $this->lastSynced(),
            'outOfStock' => Product::where('is_active', true)->where('stock', '<=', 0)->count(),
            'lowStock' => Product::where('is_active', true)
                ->whereRaw('stock <= COALESCE(low_stock_threshold, ?)', [5])->count(),
            'activeProducts' => Product::where('is_active', true)->count(),
            'lowStockList' => $lowStockList,
        ];
    }

    /** @return array{at:string|null, minutes:int|null, stale:bool} */
    private function lastSynced(): array
    {
        $raw = Setting::get(SmaccImportService::LAST_SYNCED_KEY);
        if (! $raw) {
            return ['at' => null, 'minutes' => null, 'stale' => true];
        }

        $at = Carbon::parse($raw);
        // Pass elapsed minutes; the client picks the friendly unit (min/hours/days/weeks).
        $minutes = (int) $at->diffInMinutes(now());

        return ['at' => $at->toDateTimeString(), 'minutes' => $minutes, 'stale' => $minutes >= 24 * 60];
    }

    /**
     * Best-selling products over the last 30 days (paid orders), by units sold.
     * Names come from the order-line snapshots so deleted products still show.
     *
     * @return list<array<string, mixed>>
     */
    private function topProducts(): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id as product_id, MAX(order_items.product_name_ar) as name_ar, MAX(order_items.product_name_en) as name_en, SUM(order_items.quantity) as qty, SUM(order_items.line_total) as revenue')
            ->orderByDesc('qty')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'product_id' => (int) $r->product_id,
                'name_ar' => $r->name_ar,
                'name_en' => $r->name_en,
                'qty' => (int) $r->qty,
                'revenue' => (float) $r->revenue,
            ])->all();
    }

    /**
     * Most requested-but-unavailable products (last 90 days) from demand_events —
     * guides restocking. Names resolved from products (incl. soft-deleted).
     *
     * @return list<array<string, mixed>>
     */
    private function demand(): array
    {
        $rows = DemandEvent::query()
            ->whereNotNull('product_id')
            ->where('created_at', '>=', now()->subDays(90))
            ->groupBy('product_id')
            ->selectRaw('product_id, COUNT(*) as cnt')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        $names = Product::withTrashed()
            ->whereIn('id', $rows->pluck('product_id'))
            ->get(['id', 'name_ar', 'name_en'])
            ->keyBy('id');

        return $rows->map(fn ($r) => [
            'product_id' => (int) $r->product_id,
            'name_ar' => $names->get($r->product_id)?->name_ar,
            'name_en' => $names->get($r->product_id)?->name_en,
            'count' => (int) $r->cnt,
        ])->all();
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function recentOrders()
    {
        return Order::query()
            ->latest()
            ->limit(6)
            ->get(['order_number', 'customer_name', 'status', 'total', 'created_at'])
            ->map(fn (Order $o) => [
                'order_number' => $o->order_number,
                'customer_name' => $o->customer_name,
                'status' => $o->status->value,
                'total' => (float) $o->total,
                'created_at' => $o->created_at?->toDateString(),
            ]);
    }
}
