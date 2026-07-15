<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use Inertia\Inertia;

/**
 * Admin landing page: at-a-glance counts of what needs attention (orders to
 * confirm, returns to review, low stock) plus the latest orders. Read-only.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $recentOrders = Order::query()
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

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'awaitingConfirmation' => Order::where('status', OrderStatus::AwaitingConfirmation)->count(),
                'pendingPayment' => Order::where('status', OrderStatus::PendingPayment)->count(),
                'returnsToReview' => OrderReturn::where('status', ReturnStatus::Requested)->count(),
                // Respect the per-product threshold, default 5 when unset.
                'lowStock' => Product::where('is_active', true)
                    ->whereRaw('stock <= COALESCE(low_stock_threshold, ?)', [5])
                    ->count(),
                'activeProducts' => Product::where('is_active', true)->count(),
                'customers' => User::where('role', 'customer')->count(),
            ],
            'recentOrders' => $recentOrders,
        ]);
    }
}
