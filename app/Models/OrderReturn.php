<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperOrderReturn
 */
class OrderReturn extends Model
{
    protected $table = 'order_returns';

    protected $fillable = [
        'order_id',
        'user_id',
        'status',
        'reason',
        'photos',
        'resolution',
        'refund_amount',
        'refund_shipping',
        'admin_notes',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'status' => ReturnStatus::class,
        'photos' => 'array',
        'refund_amount' => 'decimal:2',
        'refund_shipping' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
