<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\LoyaltyReward;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-time "write a review, get a discount" reward. Admin-gated (off by default);
 * when a verified buyer posts a review WITH a comment, they earn a single
 * percentage coupon (10% or 20%, admin-chosen) bound to their account and valid
 * for 30 days. Issued once per customer, ever — enforced by the loyalty_rewards
 * unique key (user_id, type='review', threshold). Mirrors LoyaltyService.
 */
class ReviewRewardService
{
    public const TYPE = 'review';

    public const VALIDITY_DAYS = 30;

    /** The only percentages the admin may pick. */
    public const VALID_PERCENTS = [10, 20];

    public function enabled(): bool
    {
        return Setting::get('review_reward_enabled') === '1';
    }

    /** The admin-chosen discount, clamped to an allowed value. */
    public function percent(): int
    {
        $p = (int) Setting::get('review_reward_percent', 10);

        return in_array($p, self::VALID_PERCENTS, true) ? $p : 10;
    }

    public function alreadyIssued(User $user): bool
    {
        return LoyaltyReward::where('user_id', $user->id)->where('type', self::TYPE)->exists();
    }

    /** Whether this user could still earn the reward (drives the storefront nudge). */
    public function availableFor(?User $user): bool
    {
        return $user !== null && $this->enabled() && ! $this->alreadyIssued($user);
    }

    /**
     * Issue the one-time reward coupon for a user. No-ops when the feature is off
     * or the user already claimed it. Row-locked + idempotent (safe to call from
     * the review-submit path without double-issuing).
     */
    public function issueFor(User $user): ?LoyaltyReward
    {
        if (! $this->enabled()) {
            return null;
        }

        return DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            if (! $locked) {
                return null;
            }

            $existing = LoyaltyReward::where('user_id', $locked->id)->where('type', self::TYPE)->first();
            if ($existing) {
                return $existing;
            }

            $coupon = Coupon::create([
                'code' => $this->uniqueCode(),
                'type' => CouponType::Percentage,
                'value' => $this->percent(),
                'usage_limit' => 1,
                'per_user_limit' => 1,
                'channel' => 'online',
                'source' => self::TYPE,
                'user_id' => $locked->id,
                'expires_at' => now()->addDays(self::VALIDITY_DAYS),
                'is_active' => true,
            ]);

            return LoyaltyReward::create([
                'user_id' => $locked->id,
                'type' => self::TYPE,
                'threshold' => 0, // n/a for a review reward; the unique key needs a value
                'coupon_id' => $coupon->id,
                'issued_at' => now(),
            ]);
        });
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'REVIEW-'.strtoupper(Str::random(6));
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }
}
