<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\TrustProxiedClientIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pins the client-IP resolution behind Cloudflare → Railway.
 *
 * The header sets below are VERBATIM captures from production's edge log on
 * 2026-08-11, not invented fixtures — that is the point of the test. Before the
 * fix, every one of these resolved to a Railway edge IP instead of the visitor,
 * so all `throttle:` buckets (which key on the client IP) shared one identity
 * and every IP recorded on a product request or contact message was wrong.
 *
 * @see TrustProxiedClientIp
 */
class ClientIpResolutionTest extends TestCase
{
    /** The Railway edge → container hop. REMOTE_ADDR is always private here. */
    private const RAILWAY_HOP = '100.64.0.4';

    /**
     * Real traffic from a Jordanian visitor through Cloudflare. Note the visitor's
     * address (46.248.205.155) appears NOWHERE in X-Forwarded-For: the first entry
     * is a Cloudflare egress IP, the second a Railway edge IP.
     */
    public function test_it_resolves_the_visitor_ip_for_traffic_through_cloudflare(): void
    {
        $ip = $this->resolveIpFor([
            'X-Forwarded-For' => '172.69.36.130, 79.127.178.82',
            'X-Real-IP' => '46.248.205.155',
            'CF-Connecting-IP' => '46.248.205.155',
            'X-Forwarded-Proto' => 'https',
        ]);

        $this->assertSame('46.248.205.155', $ip);
    }

    /**
     * Same visitor reaching the Railway edge directly, bypassing Cloudflare (the
     * origin is routable by Host header). Railway still reports the true client.
     */
    public function test_it_resolves_the_visitor_ip_when_cloudflare_is_bypassed(): void
    {
        $ip = $this->resolveIpFor([
            'X-Forwarded-For' => '46.248.205.155, 212.102.36.193',
            'X-Real-IP' => '46.248.205.155',
            'X-Forwarded-Proto' => 'https',
        ]);

        $this->assertSame('46.248.205.155', $ip);
    }

    /**
     * The regression that matters most: two visitors behind the SAME Cloudflare
     * edge must not share a rate-limit identity. Previously both resolved to the
     * Railway edge IP they had in common, so one shopper could exhaust another's
     * checkout limit.
     */
    public function test_two_visitors_behind_one_cloudflare_edge_get_distinct_identities(): void
    {
        $shared = [
            'X-Forwarded-For' => '172.69.36.130, 79.127.178.82',
            'X-Forwarded-Proto' => 'https',
        ];

        $first = $this->resolveIpFor($shared + ['X-Real-IP' => '46.248.205.155']);
        $second = $this->resolveIpFor($shared + ['X-Real-IP' => '188.55.10.20']);

        $this->assertSame('46.248.205.155', $first);
        $this->assertSame('188.55.10.20', $second);
        $this->assertNotSame($first, $second);
    }

    /**
     * CF-Connecting-IP is forgeable by anyone who reaches the origin directly
     * (verified against production: a forged value passed through Railway
     * untouched), so it must never be used as the source of truth.
     */
    public function test_a_forged_cf_connecting_ip_is_ignored(): void
    {
        $ip = $this->resolveIpFor([
            'X-Forwarded-For' => '46.248.205.155, 212.102.36.193',
            'X-Real-IP' => '46.248.205.155',
            'CF-Connecting-IP' => '198.51.100.22',
        ]);

        $this->assertSame('46.248.205.155', $ip);
    }

    /**
     * X-Real-IP is only honoured from a private proxy hop. A request that reached
     * the app without traversing one cannot nominate its own address.
     */
    public function test_x_real_ip_from_a_public_remote_addr_is_ignored(): void
    {
        $ip = $this->resolveIpFor(
            ['X-Real-IP' => '203.0.113.99'],
            remoteAddr: '203.0.113.7',
        );

        $this->assertSame('203.0.113.7', $ip);
    }

    public function test_a_malformed_x_real_ip_is_ignored(): void
    {
        $ip = $this->resolveIpFor([
            'X-Forwarded-For' => '46.248.205.155',
            'X-Real-IP' => 'not-an-ip',
        ]);

        $this->assertSame('46.248.205.155', $ip);
    }

    /**
     * Guards the reason the trusted-header set must keep X-Forwarded-Proto: if
     * HTTPS detection breaks, Laravel generates http:// URLs behind the proxy.
     */
    public function test_forwarded_proto_is_still_trusted(): void
    {
        $secure = null;

        Route::get('/__proto-probe', function (Request $request) use (&$secure) {
            $secure = $request->isSecure();

            return 'ok';
        });

        $this->withServerVariables(['REMOTE_ADDR' => self::RAILWAY_HOP])
            ->get('/__proto-probe', ['X-Forwarded-Proto' => 'https', 'X-Real-IP' => '46.248.205.155']);

        $this->assertTrue($secure);
    }

    /**
     * Runs a request through the real global middleware stack (TrustProxiedClientIp
     * then TrustProxies) and reports what the app resolves as the client IP.
     */
    private function resolveIpFor(array $headers, string $remoteAddr = self::RAILWAY_HOP): ?string
    {
        $resolved = null;

        Route::get('/__ip-probe', function (Request $request) use (&$resolved) {
            $resolved = $request->ip();

            return 'ok';
        });

        $this->withServerVariables(['REMOTE_ADDR' => $remoteAddr])->get('/__ip-probe', $headers);

        return $resolved;
    }
}
