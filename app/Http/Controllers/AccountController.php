<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Order;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Customer self-service account (storefront, AR-first): order history, loyalty
 * progress toward the 5→15% reward, and profile completion (a WhatsApp-only
 * signup starts with just a phone).
 */
class AccountController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();

        $orders = $user->orders()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Order $o) => [
                'order_number' => $o->order_number,
                'status' => $o->status->value,
                'payment_status' => $o->payment_status->value,
                'total' => (float) $o->total,
                'created_at' => $o->created_at?->toDateString(),
            ]);

        $count = (int) $user->confirmed_purchases_count;
        $milestone = LoyaltyService::PURCHASE_MILESTONE;
        $progress = $count % $milestone;

        // Unused loyalty reward coupons bound to this account.
        $rewards = Coupon::where('user_id', $user->id)
            ->where('source', 'loyalty')
            ->where('is_active', true)
            ->get()
            ->filter(fn (Coupon $c) => (int) $c->used_count < (int) ($c->usage_limit ?? 1))
            ->map(fn (Coupon $c) => ['code' => $c->code, 'value' => (float) $c->value])
            ->values();

        return Inertia::render('account/dashboard', [
            'profile' => $this->profilePayload(),
            'orders' => $orders,
            'loyalty' => [
                'confirmed_purchases' => $count,
                'milestone' => $milestone,
                'progress' => $progress,
                'remaining' => $progress === 0 && $count > 0 ? 0 : $milestone - $progress,
                'reward_percent' => LoyaltyService::REWARD_PERCENT,
                'rewards' => $rewards,
            ],
        ]);
    }

    public function editProfile()
    {
        return Inertia::render('account/profile', [
            'profile' => $this->profilePayload(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'city' => ['nullable', 'string', 'max:255'],
            'whatsapp_opt_in' => ['boolean'],
        ]);

        // Stamp the consent moment when opt-in flips on (needed for marketing compliance).
        if (($data['whatsapp_opt_in'] ?? false) && ! $user->whatsapp_opt_in) {
            $user->whatsapp_opt_in_at = now();
        }

        $user->fill([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
            'city' => $data['city'] ?? $user->city,
            'whatsapp_opt_in' => $data['whatsapp_opt_in'] ?? false,
        ])->save();

        return back()->with('success', __('messages.profile.updated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(): array
    {
        $user = Auth::user();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'city' => $user->city,
            'phone_verified' => $user->phone_verified_at !== null,
            'whatsapp_opt_in' => (bool) $user->whatsapp_opt_in,
        ];
    }
}
