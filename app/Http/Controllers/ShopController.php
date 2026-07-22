<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\ClientReview;
use App\Models\OrderItem;
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
    /** Curated homepage (`/`). Full product browsing lives at /shop (catalogue). */
    public function index(): Response
    {
        return Inertia::render('shop/index', [
            'bestSellers' => $this->bestSellers(),
            'newArrivals' => Product::where('is_active', true)
                ->with(['category:id,name_ar,name_en,slug', 'images'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (Product $p) => $this->card($p))
                ->all(),
            'featuredCategories' => Category::where('is_active', true)
                ->whereNotNull('image')
                ->orderBy('sort_order')
                ->get(['id', 'name_ar', 'name_en', 'slug', 'image'])
                ->all(),
            // Random handful of the active pool → rotates on each refresh.
            'reviews' => ClientReview::where('is_active', true)
                ->inRandomOrder()
                ->limit(4)
                ->get(['id', 'author_name', 'body', 'rating'])
                ->all(),
        ]);
    }

    /**
     * Full catalogue (`/shop`) — active products with optional category filter,
     * text search, on-sale ("Offers") filter, and sorting, paginated 12 per page.
     * Filters compose and are preserved across pagination (withQueryString).
     */
    public function catalogue(Request $request): Response
    {
        $activeCategory = $request->query('category');
        $search = trim((string) $request->query('q', ''));
        $sort = in_array($request->query('sort'), ['price_asc', 'price_desc', 'name'], true)
            ? $request->query('sort')
            : 'newest';
        $onSaleOnly = $request->boolean('on_sale');

        // Resolve only the filtered category's id — cheap, and it runs on the
        // partial (filter) reloads too, unlike the full chip list below.
        $categoryId = $activeCategory
            ? Category::where('is_active', true)->where('slug', $activeCategory)->value('id')
            : null;

        // Include Coming-Soon (hidden-but-surfaced) products alongside live ones;
        // they render as request-only cards. Buyability is still is_active-gated.
        $query = Product::visibleOnStore()
            ->with(['category:id,name_ar,name_en,slug', 'images']);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($search !== '') {
            // Bound as a parameter (safe); % / _ act as wildcards, which is fine for search.
            $query->where(fn ($q) => $q
                ->where('name_ar', 'like', "%{$search}%")
                ->orWhere('name_en', 'like', "%{$search}%"));
        }

        if ($onSaleOnly) {
            $query->onSale();
        }

        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name' => $query->orderBy('name_ar'),
            default => $query->orderByDesc('is_featured')->latest(),
        };

        $products = $query->paginate(12)->withQueryString()
            ->through(fn (Product $p) => $this->card($p));

        return Inertia::render('shop/catalogue', [
            // Deferred (closure): the chip list never changes while filtering, so
            // it's skipped on the partial reloads (which request only products /
            // filters / activeCategory) and sent only on the full first load.
            // Only categories that hold a visible product become chips — this keeps
            // the empty parent nav groups (التمور / الهدايا, which exist just to drive
            // the navbar dropdowns) and any empty leaf out of the filter, so a chip
            // never lands on an empty result.
            'categories' => fn () => Category::where('is_active', true)
                ->whereHas('products', fn ($q) => $q->visibleOnStore())
                ->orderBy('sort_order')
                ->get(['id', 'name_ar', 'name_en', 'slug']),
            'products' => $products,
            'activeCategory' => $activeCategory,
            'filters' => [
                'q' => $search,
                'sort' => $sort,
                'on_sale' => $onSaleOnly,
            ],
        ]);
    }

    /**
     * Live search suggestions (typeahead): up to 8 storefront-visible products
     * matching the term by name, each with a thumbnail. JSON, fetched as the
     * customer types on the catalogue; buyable products rank above Coming-Soon.
     */
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $results = Product::visibleOnStore()
            ->where(fn ($q) => $q->where('name_ar', 'like', "%{$term}%")->orWhere('name_en', 'like', "%{$term}%"))
            ->with('images')
            ->orderByDesc('is_active')
            ->limit(8)
            ->get()
            ->map(fn (Product $p) => [
                'slug' => $p->slug,
                'name_ar' => $p->name_ar,
                'name_en' => $p->name_en,
                'image' => Media::url($p->primaryImage()?->path),
                'price' => (float) $p->price,
                'effective_price' => $p->effectivePrice(),
                'on_sale' => $p->isOnSale(),
                'coming_soon' => $p->isComingSoon(),
            ]);

        return response()->json(['results' => $results]);
    }

    /**
     * Top products for the homepage "best sellers" strip: ranked by units sold in
     * orders that reached a fulfilled state, then featured, then newest — so the
     * strip shows a sensible line-up even before any real sales exist.
     *
     * @return array<int, array<string, mixed>>
     */
    private function bestSellers(): array
    {
        $soldStatuses = [
            OrderStatus::Confirmed->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        return Product::where('is_active', true)
            ->with(['category:id,name_ar,name_en,slug', 'images'])
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
            ->limit(10)
            ->get()
            ->map(fn (Product $p) => $this->card($p))
            ->all();
    }

    public function show(Request $request, Product $product, ReviewService $reviewService): Response
    {
        // Coming-Soon products are viewable (request-only); everything else hidden 404s.
        abort_unless($product->is_active || $product->is_coming_soon, 404);

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

        // Units sold across fulfilled orders — social proof ("purchased N times").
        $purchaseCount = (int) OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->whereIn('status', [
                OrderStatus::Confirmed->value,
                OrderStatus::Shipped->value,
                OrderStatus::Delivered->value,
            ]))
            ->sum('quantity');

        return Inertia::render('shop/product', [
            'product' => [
                'id' => $product->id,
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'description_ar' => $product->description_ar,
                'description_en' => $product->description_en,
                'price' => (float) $product->price,
                'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
                'effective_price' => $product->effectivePrice(),
                'on_sale' => $product->isOnSale(),
                'in_stock' => $product->stock > 0,
                'coming_soon' => $product->isComingSoon(),
                'purchase_count' => $purchaseCount,
                'category' => $product->category?->only('name_ar', 'name_en', 'slug'),
                'images' => $images,
                'url' => route('shop.product', $product->slug), // absolute, for JSON-LD/OG
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
            'coming_soon' => $product->isComingSoon(),
            'image' => Media::url($product->primaryImage()?->path),
            'category' => $product->category?->only('name_ar', 'name_en', 'slug'),
        ];
    }
}
