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

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls->map(function (array $url) {
                $lastmod = $url['lastmod'] ? "<lastmod>{$url['lastmod']}</lastmod>" : '';

                return '  <url><loc>'.e($url['loc'])."</loc>{$lastmod}</url>";
            })->implode("\n")
            ."\n</urlset>";

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
        ];

        // 🔑 When the site is set to noindex (SITE_INDEXABLE=false) it stays
        // CRAWLABLE on purpose — we deliberately do NOT emit `Disallow: /`.
        //
        // The two directives work at different layers and fight each other:
        // robots.txt controls FETCHING, noindex controls INDEXING. A crawler
        // blocked from fetching a page can never read its noindex header, so
        // Google may still list a URL it discovered through a link (as a bare,
        // snippet-less result) and is then slow to drop it. Letting the crawler
        // in to READ the noindex is what actually keeps the store out.
        //
        // The Sitemap: line is dropped though — no reason to hand over 87
        // product URLs we are asking not to be indexed.
        if (config('retab.indexable')) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('seo.sitemap');
        } else {
            array_unshift($lines, '# Pre-launch: every response sends X-Robots-Tag: noindex.');
        }

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
