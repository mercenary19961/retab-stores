<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * Two levels, and a category is EITHER a group or a leaf, never both:
 *
 *  - a GROUP is top-level and holds subcategories (التمور / الهدايا). It exists to
 *    open a navbar dropdown and holds no products of its own;
 *  - a LEAF holds products. It sits under a group, or at the top level on its own
 *    (العروض الخاصة).
 *
 * The admin (Admin\CategoryController) enforces that shape, and the product form
 * offers leaves only, so the rule is what keeps the navbar from growing a third
 * level it cannot render and a product from being filed under a dropdown heading.
 *
 * @mixin IdeHelperCategory
 */
class Category extends Model
{
    /** Media-disk folder for uploaded tile images. */
    public const IMAGE_DIR = 'categories';

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

    /**
     * Public URL of the homepage tile image.
     *
     * 🔑 `image` holds two kinds of value. The tiles that shipped with the repo live
     * under `public/` and are stored as a web path (`/images/categories/rusks.webp`);
     * anything uploaded from the admin is a media-disk key (`categories/<uuid>.png`)
     * that must go through Media so it resolves to R2 in production. Rendering the
     * raw value would work for the first kind and 404 for the second.
     */
    public function imageUrl(): ?string
    {
        $image = $this->image;

        if (! $image) {
            return null;
        }

        return self::isUploadedImage($image) ? Media::url($image, 'card') : $image;
    }

    /** True for a media-disk key, false for a shipped `/images/...` web path. */
    public static function isUploadedImage(?string $image): bool
    {
        return $image !== null && $image !== ''
            && ! str_starts_with($image, '/')
            && ! str_starts_with($image, 'http');
    }

    /**
     * The Special Offers bucket. Store events find it BY SLUG
     * (StoreEvent::offersCategory), so its slug and level are locked and it
     * cannot be deleted — changing either would make the next event silently
     * create a second one.
     */
    public function isOffersBucket(): bool
    {
        return $this->slug === StoreEvent::OFFERS_CATEGORY_SLUG;
    }

    /**
     * Why this category cannot be deleted, as a `messages.admin.*` key, or null
     * when it can.
     *
     * 🔴 `products.category_id` is `cascadeOnDelete`, so deleting a category that
     * still holds products would delete the PRODUCTS with it. The product count
     * therefore includes soft-deleted ones: a trashed product is still restorable
     * from the change log, and the cascade would hard-delete it out from under that.
     *
     * Counts can be passed in from a list that already loaded them, so the index
     * page does not query per row.
     */
    public function deletionBlocker(?int $allProducts = null, ?int $children = null): ?string
    {
        if ($this->isOffersBucket()) {
            return 'category_protected';
        }

        $children ??= $this->children()->count();
        if ($children > 0) {
            return 'category_has_children';
        }

        $allProducts ??= Product::withTrashed()->where('category_id', $this->id)->count();
        if ($allProducts > 0) {
            return 'category_not_empty';
        }

        return null;
    }
}
