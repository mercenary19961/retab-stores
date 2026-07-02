<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Bilingual CMS pages (returns policy / about / contact, extensible). AR body is
 * required (storefront falls back to AR); EN optional. Slugs are stable keys the
 * storefront links against — changing one changes the public URL.
 */
class ContentPageController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/content-pages/index', [
            'pages' => ContentPage::orderBy('slug')->get(['id', 'slug', 'title_ar', 'title_en', 'is_published', 'updated_at'])
                ->map(fn (ContentPage $p) => [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'title_ar' => $p->title_ar,
                    'title_en' => $p->title_en,
                    'is_published' => $p->is_published,
                    'updated_at' => $p->updated_at?->toDateTimeString(),
                ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/content-pages/form', ['page' => null]);
    }

    public function store(Request $request)
    {
        ContentPage::create($this->validated($request));

        return redirect()->route('admin.content-pages.index')->with('success', __('messages.admin.page_saved'));
    }

    public function edit(ContentPage $contentPage)
    {
        return Inertia::render('admin/content-pages/form', [
            'page' => $contentPage->only('id', 'slug', 'title_ar', 'title_en', 'body_ar', 'body_en', 'is_published'),
        ]);
    }

    public function update(Request $request, ContentPage $contentPage)
    {
        $contentPage->update($this->validated($request, $contentPage));

        return redirect()->route('admin.content-pages.index')->with('success', __('messages.admin.page_saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ContentPage $page = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', 'unique:content_pages,slug' . ($page ? ",{$page->id}" : '')],
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'body_ar' => ['required', 'string'],
            'body_en' => ['nullable', 'string'],
            'is_published' => ['required', 'boolean'],
        ]);
    }
}
