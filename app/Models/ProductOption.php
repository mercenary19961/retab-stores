<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sellable option of a product (a size such as 250g / 500g / 1kg, or a
 * packaging such as a carton). See the create_product_options migration for the
 * pricing + stock model.
 *
 * @mixin IdeHelperProductOption
 */
class ProductOption extends Model
{
    protected $fillable = [
        'label_ar',
        'label_en',
        'amount',
        'price',
        'price_overridden',
        'stock_units',
        'is_active',
        'sort_order',
        'smacc_sku',
    ];

    protected $casts = [
        'amount' => 'integer',
        'price' => 'decimal:2',
        'price_overridden' => 'boolean',
        'stock_units' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
