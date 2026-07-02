<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperLoyaltyReward
 */
class LoyaltyReward extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'threshold',
        'coupon_id',
        'issued_at',
        'notified_at',
    ];

    protected $casts = [
        'threshold' => 'integer',
        'issued_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
