<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController
{
    public function __construct(protected CartService $cart) {}

    public function show(): Response
    {
        return Inertia::render('shop/cart', $this->cart->summary());
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::where('is_active', true)->findOrFail($data['product_id']);
        $this->cart->add($product, $data['quantity'] ?? 1);

        return back()->with('success', 'تمت الإضافة إلى السلة');
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->ensureOwned($item);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0']]);
        $this->cart->updateQuantity($item, $data['quantity']);

        return back();
    }

    public function remove(CartItem $item): RedirectResponse
    {
        $this->ensureOwned($item);
        $this->cart->remove($item);

        return back();
    }

    private function ensureOwned(CartItem $item): void
    {
        abort_unless($item->cart_id === $this->cart->current()->id, 403);
    }
}
