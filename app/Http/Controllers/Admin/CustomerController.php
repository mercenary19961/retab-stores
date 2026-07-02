<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Back-office customer directory: who buys, their loyalty progress toward the
 * 5→15% reward, and their WhatsApp marketing opt-in (consent stamp matters for
 * Meta compliance). Read-only — customer data is edited by the customer.
 */
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $optIn = $request->query('opt_in');

        // Customers = non-staff accounts.
        $query = User::where(fn ($q) => $q->whereNull('role')->orWhereNotIn('role', ['admin', 'editor']))
            ->latest();

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        if ($optIn !== null && $optIn !== '') {
            $query->where('whatsapp_opt_in', (bool) $optIn);
        }

        return Inertia::render('admin/customers/index', [
            'customers' => $query->paginate(25)->withQueryString()->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'whatsapp_opt_in' => (bool) $u->whatsapp_opt_in,
                'confirmed_purchases' => (int) $u->confirmed_purchases_count,
                'created_at' => $u->created_at?->toDateString(),
            ]),
            'filters' => ['q' => $search, 'opt_in' => $optIn],
        ]);
    }

    public function show(User $customer)
    {
        abort_if(in_array($customer->role, ['admin', 'editor'], true), 404);

        $count = (int) $customer->confirmed_purchases_count;
        $milestone = LoyaltyService::PURCHASE_MILESTONE;

        return Inertia::render('admin/customers/show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'city' => $customer->city,
                'locale' => $customer->locale,
                'phone_verified' => $customer->phone_verified_at !== null,
                'whatsapp_opt_in' => (bool) $customer->whatsapp_opt_in,
                'whatsapp_opt_in_at' => $customer->whatsapp_opt_in_at?->toDateTimeString(),
                'created_at' => $customer->created_at?->toDateTimeString(),
            ],
            'loyalty' => [
                'confirmed_purchases' => $count,
                'milestone' => $milestone,
                'progress' => $count % $milestone,
                'rewards' => Coupon::where('user_id', $customer->id)
                    ->latest()
                    ->get()
                    ->map(fn (Coupon $c) => [
                        'code' => $c->code,
                        'value' => (float) $c->value,
                        'is_active' => (bool) $c->is_active,
                        'source' => $c->source,
                    ]),
            ],
            'orders' => Order::where('user_id', $customer->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (Order $o) => [
                    'order_number' => $o->order_number,
                    'status' => $o->status->value,
                    'payment_status' => $o->payment_status->value,
                    'total' => (float) $o->total,
                    'created_at' => $o->created_at?->toDateString(),
                ]),
        ]);
    }
}
