<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use Inertia\Inertia;

/**
 * Public CMS pages (returns policy / about / contact). Ships BOTH locales so the
 * AR⇄EN toggle is instant client-side (useLocalized), like product content.
 */
class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = ContentPage::where('slug', $slug)->where('is_published', true)->firstOrFail();

        return Inertia::render('shop/page', [
            'page' => $page->only('slug', 'title_ar', 'title_en', 'body_ar', 'body_en'),
        ]);
    }
}
