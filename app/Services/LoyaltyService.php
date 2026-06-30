<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Count-based loyalty: every 5 confirmed purchases earns the customer a one-time
 * 15% coupon bound to their account. Called from OrderConfirmationService when an
 * order is confirmed. Account-based — guest orders don't accrue loyalty.
 */
class LoyaltyService
{
    public const PURCHASE_MILESTONE = 5;

    public const REWARD_PERCENT = 15;

    /**
     * Record a confirmed purchase for the order's customer and, on hitting a
     * milestone (every 5th), issue the reward coupon. Returns the reward if one
     * was issued. Row-locked + idempotent per milestone.
     */
    public function recordConfirmedPurchase(Order $order): ?LoyaltyReward
    {
        if (! $order->user_id) {
            return null; // loyalty is account-based
        }

        return DB::transaction(function () use ($order) {
            $user = User::whereKey($order->user_id)->lockForUpdate()->first();
            if (! $user) {
                return null;
            }

            $user->increment('confirmed_purchases_count');
            $count = $user->confirmed_purchases_count;

            // Reward at every 5th confirmed purchase (5, 10, 15, ...).
            if ($count < self::PURCHASE_MILESTONE || ($count % self::PURCHASE_MILESTONE) !== 0) {
                return null;
            }

            // Issue once per milestone threshold.
            $existing = LoyaltyReward::where('user_id', $user->id)
                ->where('type', 'purchase_milestone')
                ->where('threshold', $count)
                ->first();
            if ($existing) {
                return $existing;
            }

            $coupon = Coupon::create([
                'code' => $this->uniqueCode(),
                'type' => CouponType::Percentage,
                'value' => self::REWARD_PERCENT,
                'usage_limit' => 1,
                'per_user_limit' => 1,
                'channel' => 'online',
                'source' => 'loyalty',
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            return LoyaltyReward::create([
                'user_id' => $user->id,
                'type' => 'purchase_milestone',
                'threshold' => $count,
                'coupon_id' => $coupon->id,
                'issued_at' => now(),
            ]);
        });
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'LOYAL-' . strtoupper(Str::random(6));
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }
}
