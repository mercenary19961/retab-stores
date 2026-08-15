<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Builds the `<link rel="canonical">` target for the current request.
 *
 * Why this needs policy rather than a bare url()->current(): the catalogue is
 * one path reachable under many query strings, and search engines treat each
 * distinct URL as a separate page. Left alone, `/shop`, `/shop?sort=price_asc`,
 * `/shop?q=تمر` and every `?fbclid=…` a shared WhatsApp link picks up all look
 * like duplicates of each other, which splits ranking signals across URLs that
 * hold the same products.
 *
 * The URL is built through the `url()` generator, NOT `$request->url()`, so it
 * inherits `URL::forceRootUrl(config('app.url'))` (see AppServiceProvider) and
 * always names the canonical host — the entire point of the tag. Reading it off
 * the request would make the site canonicalise to whatever host was asked for,
 * which is exactly the self-canonicalising duplicate we already guard against.
 */
class CanonicalUrl
{
    /**
     * Path prefixes that are never indexed. Mirrors SeoController::robots() —
     * keep the two lists in step; a canonical tag on a private page is noise at
     * best, and at worst points a crawler at something we asked it to skip.
     */
    private const PRIVATE_PREFIXES = ['admin', 'account', 'cart', 'checkout', 'orders', 'wishlist', 'login', 'register'];

    /**
     * Query params that yield a genuinely distinct, index-worthy page. Everything
     * else is dropped, which is deliberate per param:
     *   `sort`    — same products, different order ⇒ a duplicate of the base URL.
     *   `q`       — search results are unbounded and worthless as landing pages.
     *   tracking  — utm_*, fbclid, gclid: the classic reason canonical exists.
     */
    private const INDEXABLE_QUERY = ['category', 'on_sale', 'page'];

    /** The canonical URL for this request, or null when the page isn't indexable. */
    public static function for(Request $request): ?string
    {
        if (! $request->isMethod('GET') || self::isPrivate($request)) {
            return null;
        }

        $params = [];

        foreach (self::INDEXABLE_QUERY as $key) {
            $value = $request->query($key);

            // Scalars only: an array param (`?category[]=x`) can't round-trip
            // into a single canonical URL, so treat it as absent.
            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }

            $params[$key] = (string) $value;
        }

        // `page=1` and a falsy `on_sale` render the SAME page as the bare URL,
        // so they have to collapse onto it rather than becoming a second URL.
        if (($params['page'] ?? null) === '1') {
            unset($params['page']);
        }

        if (isset($params['on_sale']) && ! filter_var($params['on_sale'], FILTER_VALIDATE_BOOLEAN)) {
            unset($params['on_sale']);
        }

        // Sorted so `?category=x&page=2` and `?page=2&category=x` — the same page
        // reached two ways — produce one canonical URL, not two.
        ksort($params);

        return url()->current().($params ? '?'.http_build_query($params) : '');
    }

    private static function isPrivate(Request $request): bool
    {
        foreach (self::PRIVATE_PREFIXES as $prefix) {
            // Both patterns: `admin*` would also swallow an unrelated
            // `/administrators` path, so match the segment exactly.
            if ($request->is($prefix, $prefix.'/*')) {
                return true;
            }
        }

        return false;
    }
}
