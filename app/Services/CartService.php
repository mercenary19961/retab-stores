<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
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

    public function add(Product $product, int $quantity = 1): CartItem
    {
        $quantity = max(1, $quantity);
        $cart = $this->current();
        $item = $cart->items()->where('product_id', $product->id)->first();

        if ($item) {
            $item->increment('quantity', $quantity);

            return $item;
        }

        return $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->effectivePrice(),
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

            $existing = $userCart->items()->pluck('id', 'product_id'); // product_id => item id

            foreach ($guest->items as $item) {
                if ($existing->has($item->product_id)) {
                    CartItem::whereKey($existing->get($item->product_id))->increment('quantity', $item->quantity);
                } else {
                    $userCart->items()->create([
                        'product_id' => $item->product_id,
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
     * @return array{items: \Illuminate\Support\Collection, count: int, subtotal: float}
     */
    public function summary(): array
    {
        $cart = $this->existing();

        if (! $cart) {
            return ['items' => collect(), 'count' => 0, 'subtotal' => 0.0];
        }

        $cart->load('items.product');

        $items = $cart->items->map(fn (CartItem $i) => [
            'id' => $i->id,
            'product_id' => $i->product_id,
            'name_ar' => $i->product?->name_ar,
            'name_en' => $i->product?->name_en,
            'slug' => $i->product?->slug,
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
