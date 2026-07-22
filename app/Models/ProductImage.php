<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * @mixin IdeHelperProductImage
 */
class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    protected static function booted(): void
    {
        // An image add/delete/primary-change alters a product's index thumbnail.
        static::saved(fn () => Cache::forget(Product::SEARCH_INDEX_CACHE));
        static::deleted(fn () => Cache::forget(Product::SEARCH_INDEX_CACHE));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
