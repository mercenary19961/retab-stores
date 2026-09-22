<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\ChangeLog\ChangeLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Admin coupon management: percentage / fixed / free-delivery coupons with a
 * date window (starts_at / expires_at) and usage caps (total + per-user).
 * Redemption is enforced at checkout (CheckoutService); this is the CRUD.
 */
class CouponController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/coupons/index', [
            'coupons' => Coupon::where('source', 'manual')->latest()->paginate(20)
                ->through(fn (Coupon $c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'type' => $c->type->value,
                    'value' => (float) $c->value,
                    'max_discount' => $c->max_discount !== null ? (float) $c->max_discount : null,
                    'min_order_total' => $c->min_order_total !== null ? (float) $c->min_order_total : null,
                    'usage_limit' => $c->usage_limit,
                    'used_count' => $c->used_count,
                    'per_user_limit' => $c->per_user_limit,
                    'starts_at' => $c->starts_at?->toDateTimeString(),
                    'expires_at' => $c->expires_at?->toDateTimeString(),
                    'is_active' => $c->is_active,
                    'status' => $c->status(),
                    'description_ar' => $c->description_ar,
                    'description_en' => $c->description_en,
                ]),
            // Active = usable right now (in window + under its usage cap). Counted
            // across all pages, so the summary is not just the current page.
            'activeCount' => Coupon::where('source', 'manual')->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
                ->count(),
        ]);
    }

    /** Quick on/off from the list — flips is_active without opening the editor. */
    public function toggle(Coupon $coupon, ChangeLogService $changeLog)
    {
        DB::transaction(function () use ($coupon, $changeLog) {
            $before = $coupon->attributesToArray();
            $coupon->update(['is_active' => ! $coupon->is_active]);
            $changeLog->logUpdated($coupon, $before, $coupon->code);
        });

        return back()->with('success', __($coupon->is_active ? 'messages.admin.coupon_activated' : 'messages.admin.coupon_deactivated'));
    }

    public function store(Request $request, ChangeLogService $changeLog)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $changeLog) {
            $coupon = Coupon::create($data + ['source' => 'manual', 'created_by' => Auth::id()]);
            $changeLog->logCreated($coupon, $coupon->code);
        });

        return redirect()->route('admin.coupons.index')->with('success', __('messages.admin.coupon_saved'));
    }

    public function update(Request $request, Coupon $coupon, ChangeLogService $changeLog)
    {
        $data = $this->validated($request, $coupon);

        DB::transaction(function () use ($coupon, $data, $changeLog) {
            $before = $coupon->attributesToArray();
            $coupon->update($data);
            $changeLog->logUpdated($coupon, $before, $coupon->code);
        });

        return redirect()->route('admin.coupons.index')->with('success', __('messages.admin.coupon_saved'));
    }

    public function destroy(Coupon $coupon, ChangeLogService $changeLog)
    {
        // A used coupon is part of order history (redemptions cascade on delete),
        // so retire it by deactivating instead of destroying the audit trail.
        if ($coupon->redemptions()->exists()) {
            return back()->with('error', __('messages.admin.coupon_has_redemptions'));
        }

        // Soft-deletes since 2026_09_22_200000, so Undo restores the coupon with
        // its window and caps intact. ⚠️ The trashed row still holds its CODE —
        // validated() scopes the unique rule to live rows so the client can reuse
        // it, which is the one thing a soft delete could have broken here.
        DB::transaction(function () use ($coupon, $changeLog) {
            $changeLog->logDeleted($coupon, $coupon->code);
            $coupon->delete();
        });

        return redirect()->route('admin.coupons.index')->with('success', __('messages.admin.coupon_deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $isPercentage = $request->input('type') === 'percentage';
        $isFreeShipping = $request->input('type') === 'free_shipping';

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('coupons', 'code')->whereNull('deleted_at')->ignore($coupon?->id)],
            'type' => ['required', Rule::in(['percentage', 'fixed', 'free_shipping'])],
            'value' => [$isFreeShipping ? 'nullable' : 'required', 'numeric', 'min:0', ...($isPercentage ? ['max:100'] : [])],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order_total' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['required', 'boolean'],
            'description_ar' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:255'],
        ]);

        // Normalise: free-shipping carries no subtotal discount; max_discount only
        // makes sense for percentage coupons.
        $data['code'] = strtoupper($data['code']);
        $data['value'] = $isFreeShipping ? 0 : $data['value'];
        if (! $isPercentage) {
            $data['max_discount'] = null;
        }

        return $data;
    }
}
