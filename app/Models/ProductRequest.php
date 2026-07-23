<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's "I want this" tap on a Coming-Soon product — a demand signal the
 * store follows up on (WhatsApp) and uses to prioritise stocking. Logged-in taps
 * carry user_id; guest taps carry a phone.
 *
 * @mixin IdeHelperProductRequest
 */
class ProductRequest extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'phone',
        'ip',
        'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
