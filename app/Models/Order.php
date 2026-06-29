<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'discount_total',
        'shipping_fee',
        'total',
        'currency',
        'coupon_id',
        'payment_gateway',
        'gateway_reference',
        'payment_url',
        'paid_at',
        'shipping_provider',
        'oto_id',
        'tracking_number',
        'carrier',
        'shipping_label_url',
        'admin_notes',
        'confirmed_at',
        'confirmed_by',
        'cancelled_at',
        'delivered_at',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'status' => OrderStatus::class,
        'payment_status' => PaymentStatus::class,
        'payment_method' => PaymentMethod::class,
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // Coupon model arrives in batch 4; relationship is safe to define now.
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Customer may cancel only before the admin confirms the order.
     */
    public function canBeCancelledByCustomer(): bool
    {
        return $this->status->isCancellableByCustomer();
    }

    /**
     * Within the return window (default 3 days from delivery) per the return policy.
     */
    public function isWithinReturnWindow(int $days = 3): bool
    {
        return $this->delivered_at !== null
            && $this->delivered_at->gte(now()->subDays($days));
    }
}
