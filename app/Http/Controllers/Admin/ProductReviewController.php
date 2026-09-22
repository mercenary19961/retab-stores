<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ChangeLog\ChangeLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Moderation for the product reviews customers write on the storefront.
 *
 * ⚠️ Not to be confused with ClientReviewController, which manages the homepage
 * testimonials (the ClientReview model, imported from Google). These are the real
 * `reviews` rows attached to a product and a verified order.
 *
 * 🔑 Reviews are published on arrival, not held for approval: ReviewService sets
 * `is_approved => true` because only a verified purchaser can post one, and the
 * customer is issued the review-reward coupon in the same request — holding the
 * review back would promise a reward for something nobody can see. So this page is
 * a TAKEDOWN path, not an inbox: the default state is visible, and staff hide the
 * occasional abusive or mistaken one.
 *
 * Hiding is the reversible action and the one to reach for. Deleting is offered
 * for spam, and ⚠️ it does NOT revoke a reward coupon already issued for that
 * review — the coupon is a separate, already-delivered promise.
 */
class ProductReviewController extends Controller
{
    /** Filters that map to a query constraint. Anything else is treated as "all". */
    private const STATUSES = ['published', 'hidden'];

    public function index(Request $request)
    {
        $status = $request->query('status');
        $status = in_array($status, self::STATUSES, true) ? $status : null;

        $rating = (int) $request->query('rating');
        $rating = ($rating >= 1 && $rating <= 5) ? $rating : null;

        $search = trim((string) $request->query('q'));

        $perPage = $this->perPage($request, 25);

        $reviews = Review::query()
            ->with(['product:id,name_ar,name_en,slug', 'user:id,name'])
            ->when($status === 'published', fn ($q) => $q->where('is_approved', true))
            ->when($status === 'hidden', fn ($q) => $q->where('is_approved', false))
            ->when($rating, fn ($q) => $q->where('rating', $rating))
            // Match the product, not the review body: staff look for "the reviews on
            // X", and the body is already visible in the row.
            ->when($search !== '', fn ($q) => $q->whereHas(
                'product',
                fn ($p) => $p->where('name_ar', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%")
            ))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Review $r) => [
                'id' => $r->id,
                'product' => $r->product ? [
                    'name_ar' => $r->product->name_ar,
                    'name_en' => $r->product->name_en,
                    'slug' => $r->product->slug,
                ] : null,
                'customer' => $r->user?->name,
                'rating' => (int) $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                // A review always comes from a verified purchase today, but the column
                // is nullable, so report what the row actually says rather than assuming.
                'verified' => $r->order_id !== null,
                'helpful_count' => (int) $r->helpful_count,
                'approved' => (bool) $r->is_approved,
                'created_at' => $r->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/product-reviews/index', [
            'reviews' => $reviews,
            'stats' => $this->stats(),
            'filters' => [
                'status' => $status,
                'rating' => $rating,
                'q' => $search !== '' ? $search : null,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Flip a review between visible and hidden on the storefront.
     *
     * Back-redirects so the row refreshes in place, matching every other
     * StatusToggle in the panel.
     */
    public function toggleApproval(Review $review, ChangeLogService $changeLog)
    {
        DB::transaction(function () use ($review, $changeLog) {
            $before = $review->attributesToArray();
            $review->update(['is_approved' => ! $review->is_approved]);
            $changeLog->logUpdated($review, $before, $this->label($review));
        });

        return back()->with(
            'success',
            __($review->is_approved ? 'messages.admin.review_shown' : 'messages.admin.review_hidden')
        );
    }

    public function destroy(Review $review, ChangeLogService $changeLog)
    {
        /*
         * 🔑 The votes are LEFT ALONE now, which is the opposite of what this did
         * before. They were cleared to avoid orphans pointing at a missing id —
         * but the review soft-deletes since 2026_09_22_200000, so its id is still
         * there and nothing is orphaned. Deleting them would instead mean a
         * restored review came back stripped of the helpfulness other customers
         * had voted on, which cannot be undone by anything. The cascade on
         * review_helpful_votes.review_id still fires on the eventual force-delete.
         */
        DB::transaction(function () use ($review, $changeLog) {
            $changeLog->logDeleted($review, $this->label($review));
            $review->delete();
        });

        return back()->with('success', __('messages.admin.review_deleted'));
    }

    /**
     * What the change log calls a review. It is a customer's own words about a
     * product, so naming both is what makes the entry legible weeks later.
     */
    private function label(Review $review): string
    {
        $product = $review->product?->name_ar ?? ('product #'.$review->product_id);
        $who = $review->user?->name ?: 'a customer';

        return $product.' — '.$who;
    }

    /**
     * Headline counts. `average` is over PUBLISHED reviews only, because that is
     * the number the storefront shows shoppers — an average that silently included
     * hidden reviews would not match what the product page reports.
     *
     * @return array{total:int, published:int, hidden:int, average:float|null}
     */
    private function stats(): array
    {
        $total = Review::count();
        $published = Review::where('is_approved', true)->count();
        $average = Review::where('is_approved', true)->avg('rating');

        return [
            'total' => $total,
            'published' => $published,
            'hidden' => $total - $published,
            'average' => $average !== null ? round((float) $average, 1) : null,
        ];
    }
}
