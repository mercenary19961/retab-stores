<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewHelpfulVote;
use App\Models\Wishlist;
use App\Services\ReviewService;
use App\Support\Media;
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
            ->with(['category:id,name_ar,name_en,slug', 'images'])
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

    public function show(Request $request, Product $product, ReviewService $reviewService): Response
    {
        abort_unless($product->is_active, 404);

        $product->load('category:id,name_ar,name_en,slug', 'images');
        $user = $request->user();

        $images = $product->images->sortBy('sort_order')
            ->map(fn ($img) => Media::url($img->path))
            ->filter()
            ->values();

        $reviews = Review::where('product_id', $product->id)
            ->where('is_approved', true)
            ->with('user:id,name')
            ->latest()
            ->get();

        // Which of these reviews the current user has marked helpful.
        $votedIds = $user
            ? ReviewHelpfulVote::where('user_id', $user->id)->whereIn('review_id', $reviews->pluck('id'))->pluck('review_id')->all()
            : [];

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
                'images' => $images,
            ],
            'reviews' => [
                'summary' => [
                    'count' => $reviews->count(),
                    'average' => round((float) $reviews->avg('rating'), 1),
                ],
                'items' => $reviews->map(fn (Review $r) => [
                    'id' => $r->id,
                    'rating' => $r->rating,
                    'title' => $r->title,
                    'body' => $r->body,
                    'author' => $r->user?->name ?? __('messages.review.anonymous'),
                    'helpful_count' => $r->helpful_count,
                    'voted' => in_array($r->id, $votedIds, true),
                    'is_mine' => $user && $r->user_id === $user->id,
                    'date' => $r->created_at?->toDateString(),
                ])->values(),
                'can_review' => $user ? (bool) $reviewService->eligibleOrderId($user, $product->id) : false,
            ],
            'wishlisted' => $user
                ? Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->exists()
                : false,
            'authed' => (bool) $user,
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
            'name_en' => $product->name_en,
            'slug' => $product->slug,
            'price' => (float) $product->price,
            'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
            'effective_price' => $product->effectivePrice(),
            'on_sale' => $product->isOnSale(),
            'is_featured' => (bool) $product->is_featured,
            'image' => Media::url($product->primaryImage()?->path),
            'category' => $product->category?->only('name_ar', 'name_en', 'slug'),
        ];
    }
}
