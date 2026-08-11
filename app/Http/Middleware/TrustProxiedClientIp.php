<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Rewrites X-Forwarded-For to the real visitor IP, which on this stack arrives
 * in X-Real-IP instead. Must run BEFORE Laravel's TrustProxies.
 *
 * Why this exists (measured against production on 2026-08-11, not theorised):
 * Railway's edge REPLACES whatever X-Forwarded-For the client sent with its own
 * chain, `<ip that connected to Railway>, <Railway edge hop>`. So with Cloudflare
 * in front, X-Forwarded-For reads `<Cloudflare egress IP>, <Railway edge IP>` and
 * the visitor's own address appears nowhere in it. Laravel's `trustProxies('*')`
 * trusts only the immediate caller (REMOTE_ADDR, a 100.64.x.x private hop) and so
 * resolves the *rightmost untrusted* entry — a Railway edge IP. Every visitor
 * therefore shared one identity: all `throttle:` buckets (which key on the client
 * IP) collapsed per edge node, and every recorded IP was a Railway address.
 *
 * Narrowing the trusted list cannot fix that, because the visitor IP is not in
 * the header at all. Railway does forward it, in X-Real-IP.
 *
 * Trusting X-Real-IP is sound here, both halves verified by probing production:
 *  - Sending a forged X-Real-IP (and a forged X-Forwarded-For) straight to the
 *    Railway edge, bypassing Cloudflare, arrived with BOTH overwritten by the
 *    true connecting address. Railway sanitises them, so they cannot be spoofed.
 *  - A forged CF-Connecting-IP on that same direct request passed through
 *    untouched and did NOT influence X-Real-IP. Railway only honours Cloudflare's
 *    header when the connection genuinely comes from Cloudflare. That is why this
 *    reads X-Real-IP and deliberately never reads CF-Connecting-IP, which is
 *    forgeable by anyone who reaches the origin directly.
 *
 * The header is REPLACED rather than prepended, which matters: appending the old
 * chain back would leave the Railway hop as the rightmost untrusted entry and
 * Symfony would keep returning that instead of the visitor.
 */
class TrustProxiedClientIp
{
    /**
     * Hops we will accept an X-Real-IP from. All private/CGNAT, because the only
     * thing that can reach this app is Railway's edge over its internal network
     * (REMOTE_ADDR is always 100.64.x.x in production). Loopback is deliberately
     * excluded so a request that reached the app WITHOUT a proxy — local dev, or
     * any future direct-to-container path — can never inject its own client IP.
     */
    private const PROXY_RANGES = [
        '100.64.0.0/10',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        'fc00::/7',
    ];

    public function handle(Request $request, Closure $next)
    {
        $clientIp = $request->headers->get('X-Real-IP');

        if ($clientIp !== null && filter_var($clientIp, FILTER_VALIDATE_IP) && $this->arrivedViaProxy($request)) {
            $request->headers->set('X-Forwarded-For', $clientIp);
        }

        return $next($request);
    }

    private function arrivedViaProxy(Request $request): bool
    {
        $remoteAddr = $request->server->get('REMOTE_ADDR');

        return is_string($remoteAddr) && IpUtils::checkIp($remoteAddr, self::PROXY_RANGES);
    }
}
