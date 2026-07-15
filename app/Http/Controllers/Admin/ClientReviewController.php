<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientReview;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Curated client reviews (mostly Google Maps). The admin builds the pool and
 * toggles `is_active`; the storefront shows a random active subset per request.
 */
class ClientReviewController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/client-reviews/index', [
            'reviews' => ClientReview::orderByDesc('is_active')->orderBy('sort_order')->latest()->get()
                ->map(fn (ClientReview $r) => [
                    'id' => $r->id,
                    'author_name' => $r->author_name,
                    'body' => $r->body,
                    'rating' => $r->rating,
                    'language' => $r->language,
                    'source' => $r->source,
                    'is_active' => $r->is_active,
                    'updated_at' => $r->updated_at?->toDateTimeString(),
                ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/client-reviews/form', ['review' => null]);
    }

    public function store(Request $request)
    {
        ClientReview::create($this->validated($request) + ['source' => 'manual']);

        return redirect()->route('admin.client-reviews.index')->with('success', __('messages.admin.review_saved'));
    }

    public function edit(ClientReview $clientReview)
    {
        return Inertia::render('admin/client-reviews/form', [
            'review' => $clientReview->only('id', 'author_name', 'body', 'rating', 'language', 'is_active'),
        ]);
    }

    public function update(Request $request, ClientReview $clientReview)
    {
        $clientReview->update($this->validated($request));

        return redirect()->route('admin.client-reviews.index')->with('success', __('messages.admin.review_saved'));
    }

    public function destroy(ClientReview $clientReview)
    {
        $clientReview->delete();

        return redirect()->route('admin.client-reviews.index')->with('success', __('messages.admin.review_deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'author_name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'language' => ['nullable', 'in:ar,en'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
