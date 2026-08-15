<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOrderActivity
 */
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
     *
     * `$cost` is what the carrier charges the STORE, not the flat fee the
     * customer paid. Kept per entry as well as on the order so a cancelled and
     * re-shipped order still shows what each attempt cost — the order column
     * only ever holds the current shipment.
     */
    public static function logTrackingUpdate(
        Order $order,
        string $trackingNumber,
        ?string $carrier,
        ?int $userId = null,
        float|string|null $cost = null,
        ?string $currency = null,
    ): self {
        return static::create([
            'order_id' => $order->id,
            'type' => 'tracking',
            'user_id' => $userId,
            'meta' => array_filter([
                'tracking_number' => $trackingNumber,
                'carrier' => $carrier,
                'cost' => $cost === null ? null : (float) $cost,
                'currency' => $currency,
            ], fn ($value) => $value !== null),
        ]);
    }

    /**
     * Record that a shipment was recalled from the carrier.
     *
     * Its own type rather than a plain status change: "shipped → confirmed" is
     * the only way an order moves backwards, so a bare status entry technically
     * implies a recall, but nothing on it says WHICH carrier and tracking number
     * were cancelled — which is exactly what someone auditing a double carrier
     * charge needs. from/to are still populated so the status timeline stays
     * continuous.
     *
     * @param  array{tracking_number: ?string, carrier: ?string, cost: float|string|null}  $recalled
     */
    public static function logShipmentCancelled(Order $order, ?string $fromStatus, array $recalled, ?int $userId = null): self
    {
        return static::create([
            'order_id' => $order->id,
            'type' => 'shipment_cancelled',
            'from_status' => $fromStatus,
            'to_status' => $order->status->value,
            'user_id' => $userId,
            'meta' => array_filter([
                'tracking_number' => $recalled['tracking_number'] ?? null,
                'carrier' => $recalled['carrier'] ?? null,
                'cost' => isset($recalled['cost']) ? (float) $recalled['cost'] : null,
            ], fn ($value) => $value !== null),
        ]);
    }
}
