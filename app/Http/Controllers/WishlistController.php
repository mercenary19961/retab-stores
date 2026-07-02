<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use App\Services\WishlistService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WishlistController extends Controller
{
    public function __construct(
        protected WishlistService $wishlist,
    ) {}

    public function index()
    {
        $items = Wishlist::where('user_id', Auth::id())
            ->with('product:id,name_ar,name_en,slug,price,sale_price,is_active,stock')
            ->latest()
            ->get()
            ->filter(fn (Wishlist $w) => $w->product !== null)
            ->map(fn (Wishlist $w) => [
                'id' => $w->product->id,
                'name_ar' => $w->product->name_ar,
                'name_en' => $w->product->name_en,
                'slug' => $w->product->slug,
                'price' => (float) $w->product->price,
                'sale_price' => $w->product->sale_price !== null ? (float) $w->product->sale_price : null,
                'effective_price' => $w->product->effectivePrice(),
                'in_stock' => $w->product->is_active && $w->product->stock > 0,
            ])
            ->values();

        return Inertia::render('account/wishlist', ['items' => $items]);
    }

    public function toggle(Product $product)
    {
        $this->wishlist->toggle(Auth::user(), $product);

        return back(303);
    }
}
