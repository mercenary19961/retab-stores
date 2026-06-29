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
                ->post($this->baseUrl . '/refreshToken', [
                    'refresh_token' => $this->refreshToken,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('OTO token refresh failed: ' . $response->status() . ' ' . $response->body());
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
            throw new RuntimeException("OTO {$path} failed: " . $response->status() . ' ' . $response->body());
        }

        $data = $response->json() ?? [];

        // OTO returns success:false with a message on logical failures.
        if (array_key_exists('success', $data) && $data['success'] === false) {
            throw new RuntimeException("OTO {$path} rejected: " . ($data['message'] ?? 'unknown error'));
        }

        return $data;
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
