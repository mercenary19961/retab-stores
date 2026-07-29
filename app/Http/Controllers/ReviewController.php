<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use App\Services\ReviewRewardService;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviews,
        protected ReviewRewardService $rewards,
    ) {}

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->reviews->submit(Auth::user(), $product, $data['rating'], $data['title'] ?? null, $data['body'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Review reward: a review WITH a written comment earns the one-time
        // discount (rating-only doesn't qualify). No-op when the feature is off or
        // the customer already claimed it.
        if (filled($data['body'] ?? null) && $this->rewards->availableFor(Auth::user())) {
            $reward = $this->rewards->issueFor(Auth::user());
            if ($reward?->coupon) {
                return back()->with('success', __('messages.review.reward_issued', [
                    'code' => $reward->coupon->code,
                    'percent' => (int) $reward->coupon->value,
                ]));
            }
        }

        return back()->with('success', __('messages.review.posted'));
    }

    public function helpful(Review $review)
    {
        try {
            $this->reviews->toggleHelpful(Auth::user(), $review);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }
}
