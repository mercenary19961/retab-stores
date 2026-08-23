<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\User;
use App\Support\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Resolves and mutates the visitor's cart. Logged-in customers get a cart keyed
 * by user_id; guests get one keyed by a session token. Reads (count/summary) use
 * a read-only lookup so we never create empty carts for visitors just browsing.
 */
class CartService
{
    /** Resolve (creating if needed) the current cart — for mutations. */
    public function current(): Cart
    {
        if (Auth::check()) {
            return Cart::firstOrCreate(['user_id' => Auth::id()]);
        }

        $token = Session::get('cart_token');
        if (! $token) {
            $token = (string) Str::uuid();
            Session::put('cart_token', $token);
        }

        return Cart::firstOrCreate(['session_token' => $token]);
    }

    public function add(Product $product, int $quantity = 1, ?ProductOption $option = null): CartItem
    {
        $quantity = max(1, $quantity);
        $cart = $this->current();

        // One line per (product, option): the same product under two different
        // sizes is two lines; a plain product (no option) is one line as before.
        $item = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_option_id', $option?->id)
            ->first();

        if ($item) {
            $item->increment('quantity', $quantity);

            return $item;
        }

        return $cart->items()->create([
            'product_id' => $product->id,
            'product_option_id' => $option?->id,
            'quantity' => $quantity,
            // The chosen size's effective price, or the original price when the
            // customer keeps the default (no size).
            'unit_price' => $product->priceForOption($option),
        ]);
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            $item->delete();

            return;
        }

        $item->update(['quantity' => $quantity]);
    }

    public function remove(CartItem $item): void
    {
        $item->delete();
    }

    /** Empty a cart (after its order is placed). */
    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    /**
     * Fold a guest's session cart into the user's cart at login, so items added
     * before signing in aren't lost. Same product in both → quantities combine.
     * No-op when there's no guest cart. Call right after Auth::login().
     */
    public function mergeGuestInto(User $user): void
    {
        $token = Session::get('cart_token');
        if (! $token) {
            return;
        }

        $guest = Cart::where('session_token', $token)->with('items')->first();
        if ($guest && $guest->items->isNotEmpty()) {
            $userCart = Cart::firstOrCreate(['user_id' => $user->id]);

            // Key on (product, option) so the same product under two sizes stays
            // two lines through the merge, matching add().
            $existing = $userCart->items()->get(['id', 'product_id', 'product_option_id'])
                ->keyBy(fn (CartItem $i) => $i->product_id.':'.($i->product_option_id ?? ''));

            foreach ($guest->items as $item) {
                $key = $item->product_id.':'.($item->product_option_id ?? '');
                if ($existing->has($key)) {
                    CartItem::whereKey($existing->get($key)->id)->increment('quantity', $item->quantity);
                } else {
                    $userCart->items()->create([
                        'product_id' => $item->product_id,
                        'product_option_id' => $item->product_option_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                    ]);
                }
            }

            $guest->delete(); // cascades cart_items
        }

        Session::forget('cart_token');
    }

    /**
     * @return array{items: Collection, count: int, subtotal: float}
     */
    public function summary(): array
    {
        $cart = $this->existing();

        if (! $cart) {
            return ['items' => collect(), 'count' => 0, 'subtotal' => 0.0];
        }

        // `images` is eager-loaded because primaryImage() reads the relation —
        // without it this would be a query per line item.
        $cart->load('items.product.images', 'items.option');

        $items = $cart->items->map(fn (CartItem $i) => [
            'id' => $i->id,
            'product_id' => $i->product_id,
            'option_id' => $i->product_option_id,
            'option_label_ar' => $i->option?->label_ar,
            'option_label_en' => $i->option?->label_en,
            'name_ar' => $i->product?->name_ar,
            'name_en' => $i->product?->name_en,
            'slug' => $i->product?->slug,
            // `card` variant (~15 KB WebP), not the full-res original — see
            // Architecture → File Storage → responsive variants.
            'image' => Media::url($i->product?->primaryImage()?->path, 'card'),
            'unit_price' => (float) $i->unit_price,
            'quantity' => $i->quantity,
            'line_total' => round((float) $i->unit_price * $i->quantity, 2),
        ])->values();

        return [
            'items' => $items,
            'count' => (int) $cart->items->sum('quantity'),
            'subtotal' => round((float) $cart->items->sum(fn (CartItem $i) => $i->unit_price * $i->quantity), 2),
        ];
    }

    /** Cart item count for the header — read-only (never creates a cart). */
    public function count(): int
    {
        return (int) ($this->existing()?->items()->sum('quantity') ?? 0);
    }

    private function existing(): ?Cart
    {
        if (Auth::check()) {
            return Cart::where('user_id', Auth::id())->first();
        }

        $token = Session::get('cart_token');

        return $token ? Cart::where('session_token', $token)->first() : null;
    }
}
