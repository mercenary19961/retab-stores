<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @mixin IdeHelperProduct
 */
class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name_ar',
        'name_en',
        'slug',
        'description_ar',
        'description_en',
        'short_description_ar',
        'short_description_en',
        'price',
        'sale_price',
        'sale_starts_at',
        'sale_ends_at',
        'sku',
        'smacc_sku',
        'barcode',
        'stock',
        'low_stock_threshold',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'sale_starts_at' => 'datetime',
        'sale_ends_at' => 'datetime',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * The primary image (or the first by sort order). Expects `images` loaded.
     */
    public function primaryImage(): ?ProductImage
    {
        return $this->images->firstWhere('is_primary', true)
            ?? $this->images->sortBy('sort_order')->first();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Products currently on sale — the SQL mirror of isOnSale(): a sale_price set
     * below the regular price, within its (optional) date window. Used for the
     * catalogue "Offers" filter so on-sale filtering happens in the query, not
     * post-hydration.
     */
    public function scopeOnSale(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->whereNotNull('sale_price')
            ->whereColumn('sale_price', '<', 'price')
            ->where(fn (Builder $q) => $q->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', $now));
    }

    /**
     * True when a sale price is set and actually below the regular price.
     */
    public function isOnSale(): bool
    {
        if ($this->sale_price === null || $this->sale_price >= $this->price) {
            return false;
        }

        // A null window bound means "no bound": active immediately / indefinitely.
        if ($this->sale_starts_at && $this->sale_starts_at->isFuture()) {
            return false;
        }
        if ($this->sale_ends_at && $this->sale_ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Sale lifecycle label for the admin (a sale_price is set, but the window may
     * be pending or over): scheduled / expired / active. Assumes sale_price set.
     */
    public function saleStatus(): string
    {
        if ($this->sale_starts_at && $this->sale_starts_at->isFuture()) {
            return 'scheduled';
        }
        if ($this->sale_ends_at && $this->sale_ends_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * The price the customer actually pays (sale price when on sale, else regular).
     */
    public function effectivePrice(): float
    {
        return (float) ($this->isOnSale() ? $this->sale_price : $this->price);
    }

    /**
     * Stock at or below the product threshold (falls back to 0 when unset).
     */
    public function isLowStock(): bool
    {
        return $this->stock <= ($this->low_stock_threshold ?? 0);
    }
}
