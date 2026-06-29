<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderActivity extends Model
{
    public const UPDATED_AT = null; // append-only log: created_at only

    protected $fillable = [
        'order_id',
        'type',
        'from_status',
        'to_status',
        'user_id',
        'note',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a status transition on the order's audit trail.
     */
    public static function logStatusChange(Order $order, ?string $from, ?string $to, ?int $userId = null): self
    {
        return static::create([
            'order_id' => $order->id,
            'type' => 'status_change',
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $userId,
        ]);
    }

    /**
     * Record a tracking-number / carrier update.
     */
    public static function logTrackingUpdate(Order $order, string $trackingNumber, ?string $carrier, ?int $userId = null): self
    {
        return static::create([
            'order_id' => $order->id,
            'type' => 'tracking',
            'user_id' => $userId,
            'meta' => ['tracking_number' => $trackingNumber, 'carrier' => $carrier],
        ]);
    }
}
