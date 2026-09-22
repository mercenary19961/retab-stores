<?php

namespace App\Http\Middleware;

use App\Support\Media;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if (! app()->isLocal()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->headers->set('Content-Security-Policy', $this->buildCsp());
        }

        // Pre-launch kill switch (SITE_INDEXABLE=false). Sent as a HEADER rather
        // than a <meta> tag so it also covers responses React never renders —
        // the sitemap, robots.txt itself, redirects — and needs no frontend
        // change. This, not robots.txt, is what actually keeps pages out of the
        // index; see SeoController::robots() for why crawling stays allowed.
        if (! config('retab.indexable')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    /**
     * CSP intentionally not sent in local dev — Vite's HMR origin uses bracketed
     * IPv6 syntax that Chrome rejects. 'unsafe-inline' on script-src covers the
     * inline JSON-LD blocks planned for SEO; React escapes dynamic content and we
     * never use dangerouslySetInnerHTML. Checkout redirects OUT to Moyasar/Tamara
     * hosted pages (Inertia::location), so no gateway JS/frames are allowlisted.
     */
    private function buildCsp(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com https://static.cloudflareinsights.com",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            // R2/CDN product images are https; data:/blob: for previews.
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.bunny.net",
            // Google Maps embed on the branches (locations) page.
            "frame-src 'self' https://challenges.cloudflare.com https://www.google.com https://maps.google.com",
            "connect-src 'self' https://cloudflareinsights.com",
            /*
             * 🔴 `media-src 'self'` ALONE BROKE EVERY HERO VIDEO IN PRODUCTION, and
             * it did so silently and in two different places at once.
             *
             *  - Shoppers: uploaded media is served from the R2 custom domain, which
             *    is NOT this app's origin, so every hero video was refused. The
             *    poster still rendered (img-src allows https:), so the band looked
             *    like a still and nothing anywhere reported a fault.
             *  - Staff: the admin previews a freshly chosen file through a `blob:`
             *    URL, which this directive does not permit either. That is the whole
             *    "my MP4 will not play" saga - the browser and the file were always
             *    fine, and it worked locally only because CSP is skipped there.
             *
             * 🔑 The origin is DERIVED from the same config the URLs are built from,
             * never hardcoded. The media host has already moved once (r2.dev to
             * cdn.retab.com.sa), and a literal here would have re-broken playback the
             * day it changed, with the same invisible symptom.
             */
            "media-src 'self' blob:".$this->mediaOrigin(),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    /** The origin uploaded media is actually served from, as a CSP source. */
    private function mediaOrigin(): string
    {
        $url = config('filesystems.disks.'.Media::disk().'.url');

        if (! is_string($url) || $url === '') {
            return '';
        }

        $host = parse_url($url, PHP_URL_HOST);

        // ⚠️ An unparseable or host-less value must not corrupt the whole policy:
        // a malformed directive can invalidate more than the line it appears on.
        if (! is_string($host) || $host === '') {
            return '';
        }

        return ' '.(parse_url($url, PHP_URL_SCHEME) ?: 'https').'://'.$host;
    }
}
