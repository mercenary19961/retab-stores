<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public storefront — catalogue listing + product detail. AR-first; read-only
 * for now (cart/checkout UI comes next).
 */
class ShopController
{
    public function index(Request $request): Response
    {
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name_ar', 'name_en', 'slug']);

        $activeCategory = $request->query('category');

        $query = Product::where('is_active', true)
            ->with('category:id,name_ar,name_en,slug')
            ->orderByDesc('is_featured')
            ->latest();

        if ($activeCategory && ($category = $categories->firstWhere('slug', $activeCategory))) {
            $query->where('category_id', $category->id);
        }

        return Inertia::render('shop/index', [
            'categories' => $categories,
            'products' => $query->get()->map(fn (Product $p) => $this->card($p))->values(),
            'activeCategory' => $activeCategory,
        ]);
    }

    public function show(Product $product): Response
    {
        abort_unless($product->is_active, 404);

        $product->load('category:id,name_ar,name_en,slug');

        return Inertia::render('shop/product', [
            'product' => [
                'id' => $product->id,
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'slug' => $product->slug,
                'description_ar' => $product->description_ar,
                'description_en' => $product->description_en,
                'price' => (float) $product->price,
                'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
                'effective_price' => $product->effectivePrice(),
                'on_sale' => $product->isOnSale(),
                'in_stock' => $product->stock > 0,
                'category' => $product->category?->only('name_ar', 'name_en', 'slug'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Product $product): array
    {
        return [
            'id' => $product->id,
            'name_ar' => $product->name_ar,
            'slug' => $product->slug,
            'price' => (float) $product->price,
            'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
            'effective_price' => $product->effectivePrice(),
            'on_sale' => $product->isOnSale(),
            'is_featured' => (bool) $product->is_featured,
            'category' => $product->category?->only('name_ar', 'slug'),
        ];
    }
}
