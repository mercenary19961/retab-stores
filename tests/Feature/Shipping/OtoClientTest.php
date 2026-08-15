<?php

namespace Tests\Feature\Shipping;

use App\Services\Shipping\Oto\OtoClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Auth handling for the OTO client. These pin behaviour that was verified
 * against the live API on 2026-08-15: /refreshToken answers 200 with
 * `expires_in: "3600"` and a reusable refresh token.
 */
class OtoClientTest extends TestCase
{
    private const BASE = 'https://api.tryoto.com/rest/v2';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function client(): OtoClient
    {
        return new OtoClient('refresh-token', self::BASE);
    }

    /** The real response shape, including expires_in arriving as a string. */
    private function tokenResponse(string $token = 'access-1', string $expiresIn = '3600'): array
    {
        return [
            'access_token' => $token,
            'refresh_token' => 'rotated-but-the-original-still-works',
            'success' => true,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
        ];
    }

    /**
     * 🔴 The regression that matters. The TTL used to be a hardcoded six days
     * while OTO's tokens live one hour, so the cache kept serving a dead token
     * for days and every shipping call failed. The cached entry must expire
     * inside the hour OTO actually grants.
     */
    /** The TTL the client asked the cache to hold the token for. */
    private function cachedTtl(): int
    {
        $captured = null;

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->andReturnUsing(function ($key, $value, $ttl) use (&$captured) {
            $captured = $ttl;

            return true;
        });

        $this->client()->accessToken();

        $this->assertNotNull($captured, 'the access token was never cached');

        return (int) $captured;
    }

    /**
     * 🔴 The regression that matters. The TTL used to be a hardcoded six days
     * while OTO's tokens live one hour, so the cache kept serving a dead token
     * for days and every shipping call failed. The cached entry must expire
     * inside the hour OTO actually grants.
     */
    public function test_the_access_token_is_cached_for_the_lifetime_oto_reports(): void
    {
        Http::fake([self::BASE.'/refreshToken' => Http::response($this->tokenResponse())]);

        $ttl = $this->cachedTtl();

        $this->assertLessThanOrEqual(3600, $ttl, 'cached beyond the hour OTO grants');
        $this->assertGreaterThan(0, $ttl);
        $this->assertSame(3600 - 300, $ttl, 'expected the reported hour less the safety margin');
    }

    public function test_a_cached_token_is_reused_without_re_exchanging(): void
    {
        Http::fake([self::BASE.'/refreshToken' => Http::response($this->tokenResponse())]);

        $client = $this->client();
        $client->accessToken();
        $client->accessToken();

        Http::assertSentCount(1);
    }

    /** A missing expires_in must not fall back to something long. */
    public function test_a_missing_expiry_falls_back_to_a_short_window(): void
    {
        Http::fake([self::BASE.'/refreshToken' => Http::response(['access_token' => 'access-1', 'success' => true])]);

        $this->assertLessThanOrEqual(1800, $this->cachedTtl());
    }

    /**
     * A stale cached token must self-heal. Before this, a 401 was returned to
     * the caller and the dead token stayed cached, so every subsequent call
     * failed too — orders would sit at confirmed with no shipment.
     */
    public function test_an_auth_rejection_mints_a_fresh_token_and_retries_once(): void
    {
        Http::fake([
            self::BASE.'/refreshToken' => Http::sequence()
                ->push($this->tokenResponse('stale-token'))
                ->push($this->tokenResponse('fresh-token')),
            self::BASE.'/createOrder' => Http::sequence()
                ->push(['message' => 'Unauthorized'], 401)
                ->push(['otoId' => 987, 'success' => true]),
        ]);

        $result = $this->client()->createOrder(['orderId' => 'RTB-1']);

        $this->assertSame(987, $result['otoId']);

        // The retry must carry the NEW token, not the one that was just rejected.
        Http::assertSent(fn ($request) => $request->url() === self::BASE.'/createOrder'
            && $request->hasHeader('Authorization', 'Bearer fresh-token'));
    }

    /** Two auth failures in a row is a real credential problem — don't loop. */
    public function test_a_persistent_auth_failure_gives_up_after_one_retry(): void
    {
        Http::fake([
            self::BASE.'/refreshToken' => Http::response($this->tokenResponse()),
            self::BASE.'/createOrder' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            $this->client()->createOrder(['orderId' => 'RTB-1']);
        } finally {
            // 2 createOrder attempts + the token exchanges they triggered.
            $this->assertSame(2, collect(Http::recorded())
                ->filter(fn ($pair) => $pair[0]->url() === self::BASE.'/createOrder')
                ->count());
        }
    }

    /** ping() is the readiness probe — it must report, never throw. */
    public function test_ping_reports_a_rejected_refresh_token_without_throwing(): void
    {
        Http::fake([self::BASE.'/refreshToken' => Http::response(['message' => 'bad token'], 401)]);

        $result = $this->client()->ping();

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['configured']);
        $this->assertSame('refresh token rejected', $result['message']);
    }

    public function test_ping_reports_ok_on_a_successful_exchange(): void
    {
        Http::fake([self::BASE.'/refreshToken' => Http::response($this->tokenResponse())]);

        $this->assertTrue($this->client()->ping()['ok']);
    }
}
