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
            'reviews' => ClientReview::orderByDesc('is_active')->orderBy('sort_order')->latest()->paginate(20)
                ->through(fn (ClientReview $r) => [
                    'id' => $r->id,
                    'author_name' => $r->author_name,
                    'body' => $r->body,
                    'rating' => $r->rating,
                    'language' => $r->language,
                    'source' => $r->source,
                    'is_active' => $r->is_active,
                    'updated_at' => $r->updated_at?->toDateTimeString(),
                ]),
            'activeCount' => ClientReview::where('is_active', true)->count(),
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

    public function importForm()
    {
        return Inertia::render('admin/client-reviews/import');
    }

    /**
     * Bulk-add reviews pasted one-per-line as `Author | Rating(1-5) | Review text`.
     * Rating defaults to 5 if blank/invalid; language is auto-detected (Arabic vs
     * Latin). Imported reviews are marked active and sourced 'google'.
     */
    public function importStore(Request $request)
    {
        $request->validate(['data' => ['required', 'string']]);

        $lines = preg_split('/\r\n|\r|\n/', trim((string) $request->input('data')));
        $created = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_pad(explode('|', $line, 3), 3, '');
            $author = trim($parts[0]);
            $rating = (int) trim($parts[1]);
            $body = trim($parts[2]);

            if ($author === '' || $body === '') {
                continue;
            }

            ClientReview::create([
                'author_name' => mb_substr($author, 0, 255),
                'body' => $body,
                'rating' => ($rating >= 1 && $rating <= 5) ? $rating : 5,
                'language' => preg_match('/\p{Arabic}/u', $body) ? 'ar' : 'en',
                'source' => 'google',
                'is_active' => true,
            ]);
            $created++;
        }

        return redirect()->route('admin.client-reviews.index')
            ->with('success', __('messages.admin.reviews_imported', ['count' => $created]));
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
