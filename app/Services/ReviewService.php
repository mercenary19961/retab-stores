<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewHelpfulVote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Product reviews (bilingual, AR-first) with helpful votes. Reviews are
 * verified-purchase only — a customer can review a product they actually bought
 * (an order containing it that reached confirmed/shipped/delivered). Verified
 * reviews are auto-approved, so no moderation queue is needed for v1.
 */
class ReviewService
{
    /** Order states that count as "purchased" for review eligibility. */
    private const PURCHASED_STATES = [OrderStatus::Confirmed, OrderStatus::Shipped, OrderStatus::Delivered];

    /**
     * The id of an eligible (purchased) order for this user+product, or null.
     */
    public function eligibleOrderId(User $user, int $productId): ?int
    {
        return Order::where('user_id', $user->id)
            ->whereIn('status', array_map(fn (OrderStatus $s) => $s->value, self::PURCHASED_STATES))
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->value('id');
    }

    /**
     * Create or update the user's review for a product. One review per
     * user+product; re-submitting edits it (helpful_count is preserved).
     *
     * @throws RuntimeException when the user hasn't purchased the product
     */
    public function submit(User $user, Product $product, int $rating, ?string $title, ?string $body): Review
    {
        $orderId = $this->eligibleOrderId($user, $product->id);
        if (! $orderId) {
            throw new RuntimeException('يمكنك تقييم المنتجات التي اشتريتها فقط.');
        }

        return Review::updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $user->id],
            [
                'order_id' => $orderId,
                'rating' => max(1, min(5, $rating)),
                'title' => $title,
                'body' => $body,
                'language' => 'ar',
                'is_approved' => true, // verified purchase → trusted
            ],
        );
    }

    /**
     * Toggle a helpful vote on a review (not the author's own). Maintains the
     * cached helpful_count. Returns the resulting voted state.
     *
     * @throws RuntimeException when voting on one's own review
     */
    public function toggleHelpful(User $user, Review $review): bool
    {
        if ($review->user_id === $user->id) {
            throw new RuntimeException('لا يمكنك التصويت على تقييمك.');
        }

        return DB::transaction(function () use ($user, $review) {
            $existing = ReviewHelpfulVote::where('review_id', $review->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $review->decrement('helpful_count');

                return false;
            }

            ReviewHelpfulVote::create(['review_id' => $review->id, 'user_id' => $user->id]);
            $review->increment('helpful_count');

            return true;
        });
    }
}
