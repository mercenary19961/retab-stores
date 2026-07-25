<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A legacy → current URL mapping served as a permanent (301) redirect. Seeded by
 * the slug-cleanup command (old Zid product slugs), extensible by hand for other
 * legacy paths. Resolved by RedirectController on a /products/{slug} miss.
 */
class Redirect extends Model
{
    protected $fillable = [
        'from_slug',
        'product_id',
        'to_url',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'hits' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The absolute URL to send the visitor to, or null when the destination is gone
     * (a deleted/hidden product with no fallback URL) — the caller then 404s. A
     * product target always resolves to its CURRENT slug, so a later re-clean still
     * lands correctly.
     */
    public function target(): ?string
    {
        $product = $this->product;

        if ($product && ($product->is_active || $product->is_coming_soon)) {
            return route('shop.product', $product->slug);
        }

        return $this->to_url ?: null;
    }
}
