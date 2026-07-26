<?php

namespace App\Services\Shipping\Oto;

use Illuminate\Http\Client\PendingRequest;
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

    public function __construct(
        protected string $refreshToken,
        protected string $baseUrl,
    ) {}

    public function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, $this->tokenTtl(), function () {
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

            return $token;
        });
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout(25)
            ->retry(2, 200, throw: false)
            ->baseUrl($this->baseUrl);
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
        $response = $this->client()->get('/orderDetails', ['orderId' => $orderId]);

        if (! $response->successful()) {
            throw new RuntimeException("OTO orderDetails failed: {$response->status()}");
        }

        return $response->json() ?? [];
    }

    private function post(string $path, array $payload): array
    {
        $response = $this->client()->post($path, $payload);

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

    private function tokenTtl(): int
    {
        // OTO access tokens are long-lived; cache conservatively for 6 days.
        return 60 * 60 * 24 * 6;
    }
}
