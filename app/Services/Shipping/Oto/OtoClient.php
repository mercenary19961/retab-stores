<?php

namespace App\Services\Shipping\Oto;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the OTO (Tryoto) REST API v2.
 *
 * Auth model: a long-lived refresh token (from the OTO dashboard) is exchanged
 * for a short-lived access token via /refreshToken. We cache the access token
 * for its lifetime so we don't re-auth on every call.
 */
class OtoClient
{
    private const TOKEN_CACHE_KEY = 'oto_access_token';

    /**
     * Seconds shaved off the reported lifetime so a token can't expire while a
     * request is still in flight.
     */
    private const EXPIRY_MARGIN = 300;

    /** Used only when OTO omits expires_in; deliberately shorter than the real hour. */
    private const FALLBACK_LIFETIME = 1800;

    public function __construct(
        protected string $refreshToken,
        protected string $baseUrl,
    ) {}

    public function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::acceptJson()
            ->timeout(20)
            ->post($this->baseUrl.'/refreshToken', [
                'refresh_token' => $this->refreshToken,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OTO token refresh failed: '.$response->status().' '.$response->body());
        }

        $token = $response->json('access_token') ?? $response->json('token');

        if (! $token) {
            throw new RuntimeException('OTO token refresh response missing access token.');
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, $this->tokenTtl($response->json('expires_in')));

        return $token;
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout(25)
            // Retry TRANSIENT faults only. The bare retry(2) this replaces fired
            // on any failed response, including a 401 — re-sending the very same
            // stale token, which cannot succeed and burns the attempt that
            // send() needs to retry with a *fresh* one.
            ->retry(2, 200, fn (\Throwable $e) => $this->isTransient($e), throw: false)
            ->baseUrl($this->baseUrl);
    }

    /** Worth another attempt with the same token: network blips, 5xx, throttling. */
    private function isTransient(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        return $e instanceof RequestException
            && ($e->response->status() >= 500 || $e->response->status() === 429);
    }

    public function createOrder(array $payload): array
    {
        return $this->post('/createOrder', $payload);
    }

    public function checkDeliveryFee(array $payload): array
    {
        return $this->post('/checkOTODeliveryFee', $payload);
    }

    public function createShipment(array $payload): array
    {
        return $this->post('/createShipment', $payload);
    }

    public function cancelShipment(array $payload): array
    {
        return $this->post('/cancelShipment', $payload);
    }

    public function orderDetails(string $orderId): array
    {
        $response = $this->send('get', '/orderDetails', ['orderId' => $orderId]);

        if (! $response->successful()) {
            throw new RuntimeException("OTO orderDetails failed: {$response->status()}");
        }

        return $response->json() ?? [];
    }

    /**
     * Issue a request, and on an auth rejection mint a fresh access token and
     * try once more.
     *
     * Needed because the cached token can stop working before its TTL elapses —
     * OTO can revoke it, and the clock skew between "issued" and "cached" is
     * ours to absorb. Without this a single stale token would fail every OTO
     * call until the cache entry expired on its own, silently stranding orders
     * at confirmed. Deliberately ONE retry: a genuinely bad refresh token would
     * otherwise loop.
     */
    private function send(string $method, string $path, array $data): Response
    {
        $response = $this->client()->{$method}($path, $data);

        if (in_array($response->status(), [401, 403], true)) {
            $this->forgetToken();
            $response = $this->client()->{$method}($path, $data);
        }

        return $response;
    }

    private function post(string $path, array $payload): array
    {
        $response = $this->send('post', $path, $payload);

        if (! $response->successful()) {
            throw new RuntimeException("OTO {$path} failed: ".$response->status().' '.$response->body());
        }

        $data = $response->json() ?? [];

        // OTO returns success:false with a message on logical failures.
        if (array_key_exists('success', $data) && $data['success'] === false) {
            throw new RuntimeException("OTO {$path} rejected: ".($data['message'] ?? 'unknown error'));
        }

        return $data;
    }

    /**
     * Readiness probe for `integrations:check` — never throws. Exchanges the
     * refresh token for an access token (uncached), which is the cleanest proof
     * the credential is valid.
     *
     * @return array{configured: bool, ok: bool, status: int|null, message: string}
     */
    public function ping(): array
    {
        if ($this->refreshToken === '') {
            return ['configured' => false, 'ok' => false, 'status' => null, 'message' => 'OTO_REFRESH_TOKEN not set'];
        }

        try {
            $response = Http::acceptJson()->timeout(15)
                ->post($this->baseUrl.'/refreshToken', ['refresh_token' => $this->refreshToken]);

            $token = $response->json('access_token') ?? $response->json('token');

            if ($response->successful() && $token) {
                return ['configured' => true, 'ok' => true, 'status' => $response->status(), 'message' => 'token exchange OK'];
            }

            if (in_array($response->status(), [401, 403], true)) {
                return ['configured' => true, 'ok' => false, 'status' => $response->status(), 'message' => 'refresh token rejected'];
            }

            return ['configured' => true, 'ok' => false, 'status' => $response->status(), 'message' => 'token exchange failed'];
        } catch (\Throwable $e) {
            return ['configured' => true, 'ok' => false, 'status' => null, 'message' => 'unreachable: '.$e->getMessage()];
        }
    }

    /**
     * Clear the cached access token (used after a 401 or for tests).
     */
    public function forgetToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    /**
     * How long to cache the access token, read from the exchange response.
     *
     * 🔴 This used to be a hardcoded SIX DAYS, on the assumption that OTO issues
     * long-lived tokens. It does not: /refreshToken reports `expires_in: "3600"`
     * — one hour. The cached token therefore died 1h in and every OTO call
     * (delivery rates, order push, shipment creation) would have failed for the
     * remaining ~5 days, with nothing to clear the entry. Take the lifetime from
     * the response so we can never disagree with the provider again.
     *
     * Note `expires_in` arrives as a STRING ("3600"), hence is_numeric.
     */
    private function tokenTtl(mixed $expiresIn): int
    {
        $lifetime = is_numeric($expiresIn) ? (int) $expiresIn : 0;

        if ($lifetime <= 0) {
            $lifetime = self::FALLBACK_LIFETIME;
        }

        // Never cache for zero/negative time if OTO ever reports a tiny lifetime.
        return max(60, $lifetime - self::EXPIRY_MARGIN);
    }
}
