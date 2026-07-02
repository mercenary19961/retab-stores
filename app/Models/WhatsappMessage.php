<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperWhatsappMessage
 */
class WhatsappMessage extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'recipient',
        'template',
        'category',
        'purpose',
        'status',
        'wam_id',
        'payload',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
