<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperCoupon
 */
class Coupon extends Model
{
    protected $fillable = [
        'code',
        'description_ar',
        'description_en',
        'type',
        'value',
        'min_order_total',
        'max_discount',
        'usage_limit',
        'used_count',
        'per_user_limit',
        'channel',
        'source',
        'starts_at',
        'expires_at',
        'is_active',
        'created_by',
        'user_id',
    ];

    protected $casts = [
        'type' => CouponType::class,
        'value' => 'decimal:2',
        'min_order_total' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'per_user_limit' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** When set, the coupon is restricted to this customer (e.g. a loyalty reward). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Active, within its date window, and under its total usage limit.
     * Optionally checks the minimum order total.
     */
    public function isValid(?float $orderTotal = null): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }
        if ($orderTotal !== null && $this->min_order_total !== null && $orderTotal < (float) $this->min_order_total) {
            return false;
        }

        return true;
    }

    /**
     * Discount this coupon yields on a given subtotal (capped at max_discount and
     * never more than the subtotal itself).
     */
    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === CouponType::Percentage
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return round(min($discount, $subtotal), 2);
    }
}
