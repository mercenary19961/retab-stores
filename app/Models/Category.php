<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * @mixin IdeHelperCategory
 */
class Category extends Model
{
    protected $fillable = [
        'name_ar',
        'name_en',
        'slug',
        'description_ar',
        'description_en',
        'image',
        'parent_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The search index folds each product's CATEGORY name into its haystack, so a
     * rename has to invalidate it the same way a product edit does — otherwise the
     * old name stays searchable, and the new one is not, until the 1h TTL lapses.
     *
     * ⚠️ No practical exposure today: there is no admin UI for categories, so this
     * only fires from a seeder, a migration or tinker. It exists so that adding one
     * later does not quietly reintroduce a stale index.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(Product::SEARCH_INDEX_CACHE));
        static::deleted(fn () => Cache::forget(Product::SEARCH_INDEX_CACHE));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
