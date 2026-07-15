<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Support\TableExport;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Back-office customer directory: who buys, their loyalty progress toward the
 * 5→15% reward, and their WhatsApp marketing opt-in (consent stamp matters for
 * Meta compliance). Read-only — customer data is edited by the customer.
 */
class CustomerController extends Controller
{
    /** Whitelisted sort columns for the table/export. */
    private const SORTABLE = ['name', 'phone', 'email', 'whatsapp_opt_in', 'confirmed_purchases_count', 'created_at'];

    /** Full field set for the export, in column order. */
    private const EXPORT_COLUMNS = [
        'id', 'name', 'email', 'phone', 'whatsapp_opt_in',
        'confirmed_purchases_count', 'locale', 'created_at',
    ];

    public function index(Request $request)
    {
        return Inertia::render('admin/customers/index', [
            'customers' => $this->filteredQuery($request)->paginate(25)->withQueryString()->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'whatsapp_opt_in' => (bool) $u->whatsapp_opt_in,
                'confirmed_purchases' => (int) $u->confirmed_purchases_count,
                'created_at' => $u->created_at?->toDateString(),
            ]),
            'filters' => [
                'q' => trim((string) $request->query('q', '')),
                'opt_in' => $request->query('opt_in'),
                'sort' => in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : null,
                'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
            ],
        ]);
    }

    /**
     * Shared list query for the table and export: non-staff accounts, search
     * (name/email/phone), opt-in filter, and a whitelisted sort.
     */
    private function filteredQuery(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $optIn = $request->query('opt_in');
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : null;
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return User::where(fn ($q) => $q->whereNull('role')->orWhereNotIn('role', ['admin', 'editor']))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when($optIn !== null && $optIn !== '', fn ($q) => $q->where('whatsapp_opt_in', (bool) $optIn))
            ->when($sort, fn ($q) => $q->orderBy($sort, $direction), fn ($q) => $q->latest());
    }

    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request)->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'whatsapp_opt_in' => (int) $u->whatsapp_opt_in,
            'confirmed_purchases_count' => (int) $u->confirmed_purchases_count,
            'locale' => $u->locale,
            'created_at' => $u->created_at?->toDateTimeString(),
        ]);

        return TableExport::download((string) $request->query('format'), 'customers', self::EXPORT_COLUMNS, $rows);
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
