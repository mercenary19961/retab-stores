<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;

/**
 * Per-user wishlist. The (user_id, product_id) unique index makes membership
 * idempotent; toggle adds or removes.
 */
class WishlistService
{
    /** Add or remove a product. Returns the resulting wishlisted state. */
    public function toggle(User $user, Product $product): bool
    {
        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

        return true;
    }

    /**
     * @return list<int>
     */
    public function productIds(User $user): array
    {
        return Wishlist::where('user_id', $user->id)->pluck('product_id')->all();
    }
}
