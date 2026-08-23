<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Product;

/**
 * The shared storefront "product card" payload and the best-seller query.
 *
 * Extracted from ShopController so other pages can render the same card without
 * duplicating either the shape or the sold-units definition — the cart's
 * empty-state recommendations were the first second consumer. ShopController
 * delegates here, so there is exactly one definition of each.
 */
class ProductCards
{
    /**
     * Card payload consumed by `components/store/product-card.tsx`.
     *
     * Ships BOTH locales (`name_ar`/`name_en`) because the client picks via
     * `useLocalized()`, which is what makes the AR⇄EN toggle instant.
     *
     * @return array<string, mixed>
     */
    public static function card(Product $product): array
    {
        return [
            'id' => $product->id,
            'name_ar' => $product->name_ar,
            'name_en' => $product->name_en,
            'slug' => $product->slug,
            'price' => (float) $product->price,
            'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
            // For an options product this is the cheapest option — the card shows
            // it as "from X". `has_options` tells the card to render that prefix
            // and to send the shopper to the product page to pick, rather than a
            // one-click add-to-cart.
            'effective_price' => $product->effectivePrice(),
            'has_options' => $product->hasOptions(),
            'on_sale' => $product->isOnSale(),
            'is_featured' => (bool) $product->is_featured,
            'coming_soon' => $product->isComingSoon(),
            'image' => Media::url($product->primaryImage()?->path, 'card'),
            'category' => $product->category?->only('name_ar', 'name_en', 'slug'),
        ];
    }

    /**
     * Best sellers by units actually sold on orders that reached a fulfilled
     * status — a pending/unavailable order must not count toward "best selling".
     *
     * @return array<int, array<string, mixed>>
     */
    public static function bestSellers(int $limit = 10): array
    {
        $soldStatuses = [
            OrderStatus::Confirmed->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        return Product::where('is_active', true)
            ->with(['category:id,name_ar,name_en,slug', 'images', 'activeOptions'])
            ->withSum(
                ['orderItems as units_sold' => fn ($q) => $q->whereHas(
                    'order',
                    fn ($o) => $o->whereIn('status', $soldStatuses)
                )],
                'quantity'
            )
            ->orderByDesc('units_sold')
            ->orderByDesc('is_featured')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Product $p) => self::card($p))
            ->all();
    }
}
