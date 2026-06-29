<?php

namespace App\Models;

use App\Enums\PaymentTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'gateway',
        'type',
        'amount',
        'currency',
        'status',
        'gateway_transaction_id',
        'raw',
    ];

    protected $casts = [
        'type' => PaymentTransactionType::class,
        'amount' => 'decimal:2',
        'raw' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
