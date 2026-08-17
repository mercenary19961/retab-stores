<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Order;
use App\Services\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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
                // An abandoned gateway checkout was reachable ONLY from the
                // confirmation page the customer had already navigated away
                // from, so this list showed unpaid orders with no way to act.
                'can_pay' => $o->isAwaitingGatewayPayment(),
            ]);

        $count = (int) $user->confirmed_purchases_count;
        $milestone = LoyaltyService::PURCHASE_MILESTONE;
        $progress = $count % $milestone;

        // Unused reward coupons bound to this account (loyalty + review rewards).
        $rewards = Coupon::where('user_id', $user->id)
            ->whereIn('source', ['loyalty', 'review'])
            ->where('is_active', true)
            ->get()
            ->filter(fn (Coupon $c) => (int) $c->used_count < (int) ($c->usage_limit ?? 1))
            ->map(fn (Coupon $c) => [
                'code' => $c->code,
                'value' => (float) $c->value,
                'source' => $c->source,
                'expires_at' => $c->expires_at?->toDateString(),
            ])
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
            // Drives the "set a password" block. A WhatsApp-OTP signup arrives
            // with password = null, and the shared `password.update` route
            // requires `current_password`, which they have never had.
            'has_password' => $user->password !== null,
            // A password is useless without an identifier to sign in WITH: the
            // login form is email + password, so an account with no email must
            // add one first.
            'can_set_password' => $user->password === null && filled($user->email),
        ];
    }

    /**
     * Set a FIRST password on an account that has never had one.
     *
     * 🔑 Why this cannot just reuse `password.update`: that route requires
     * `current_password`, and an account created through WhatsApp OTP has
     * `password = null`. So the one existing way to get a password was
     * unavailable to exactly the customers who most needed it — the primary
     * sign-in method for this store.
     *
     * 🔴 Guarded to `password === null`, and that guard is the whole security
     * model here. Allowing it on an account that already has a password would
     * turn a hijacked session into a permanent takeover with no knowledge of the
     * old credential. Changing an existing password stays on `password.update`,
     * where the current one must be supplied.
     */
    public function setPassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user->password === null, 403);

        // Refuse rather than silently create an unusable credential.
        if (blank($user->email)) {
            return back()->with('error', __('messages.profile.email_needed_first'));
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill(['password' => $data['password']])->save(); // 'hashed' cast

        return back()->with('success', __('messages.profile.password_set'));
    }
}
