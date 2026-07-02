<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\Product;
use Illuminate\Http\Response;

/**
 * Crawler endpoints. Both are routes (not static files) so URLs stay absolute
 * per-environment and the sitemap reflects the live catalogue. public/robots.txt
 * was removed — a static file there would shadow the route.
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect([['loc' => route('home'), 'lastmod' => null]])
            ->concat(Product::where('is_active', true)->get(['slug', 'updated_at'])
                ->map(fn (Product $p) => [
                    'loc' => route('shop.product', $p->slug),
                    'lastmod' => $p->updated_at?->toAtomString(),
                ]))
            ->concat(ContentPage::where('is_published', true)->get(['slug', 'updated_at'])
                ->map(fn (ContentPage $p) => [
                    'loc' => route('pages.show', $p->slug),
                    'lastmod' => $p->updated_at?->toAtomString(),
                ]));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . $urls->map(function (array $url) {
                $lastmod = $url['lastmod'] ? "<lastmod>{$url['lastmod']}</lastmod>" : '';

                return '  <url><loc>' . e($url['loc']) . "</loc>{$lastmod}</url>";
            })->implode("\n")
            . "\n</urlset>";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /account',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /orders',
            'Disallow: /wishlist',
            'Disallow: /login',
            'Disallow: /register',
            '',
            'Sitemap: ' . route('seo.sitemap'),
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
