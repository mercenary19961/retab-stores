<?php

namespace App\Http\Middleware;

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
            "media-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
