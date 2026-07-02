<?php

namespace App\Services\Payments\Tamara;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the Tamara REST API. Knows nothing about our orders — it
 * just speaks Tamara's protocol. Amounts here are MAJOR units (e.g. 100.00 SAR),
 * unlike Moyasar's halalas. Auth is a Bearer API token. Webhook authenticity is
 * proven by an HS256 JWT (signed with a SEPARATE notification token) carried in
 * the `tamara-token` header.
 */
class TamaraClient
{
    public function __construct(
        protected string $apiToken,
        protected string $notificationToken,
        protected string $baseUrl,
    ) {}

    private function client(): PendingRequest
    {
        return Http::withToken($this->apiToken)
            ->acceptJson()
            ->asJson()
            ->timeout(25)
            ->retry(2, 200, throw: false)
            ->baseUrl($this->baseUrl);
    }

    /** @return array{order_id:string, checkout_id:string, checkout_url:string, status:string} */
    public function createCheckout(array $payload): array
    {
        $response = $this->client()->post('/checkout', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Tamara checkout creation failed: ' . $response->status() . ' ' . $response->body()
            );
        }

        $data = $response->json();

        if (empty($data['order_id']) || empty($data['checkout_url'])) {
            throw new RuntimeException('Tamara checkout response missing order_id/checkout_url.');
        }

        return $data;
    }

    public function getOrder(string $orderId): array
    {
        $response = $this->client()->get("/orders/{$orderId}");

        if (! $response->successful()) {
            throw new RuntimeException("Tamara get order {$orderId} failed: " . $response->status());
        }

        return $response->json();
    }

    /** Commit the hold on an approved order (does NOT take the money — capture does). */
    public function authorise(string $orderId): array
    {
        $response = $this->client()->post("/orders/{$orderId}/authorise");

        // 409 = already authorised / not in an authorisable state; caller re-reads status.
        if (! $response->successful() && $response->status() !== 409) {
            throw new RuntimeException("Tamara authorise {$orderId} failed: " . $response->status() . ' ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /** Take the money on an authorised order (Retab: at admin confirmation). */
    public function capture(string $orderId, array $payload): array
    {
        $response = $this->client()->post('/payments/capture', array_merge(['order_id' => $orderId], $payload));

        if (! $response->successful()) {
            throw new RuntimeException("Tamara capture {$orderId} failed: " . $response->status() . ' ' . $response->body());
        }

        return $response->json();
    }

    /** Refund (full or partial) a captured order. Amounts in MAJOR units. */
    public function refund(string $orderId, array $payload): array
    {
        $response = $this->client()->post('/payments/simplified-refund', array_merge(['order_id' => $orderId], $payload));

        if (! $response->successful()) {
            throw new RuntimeException("Tamara refund {$orderId} failed: " . $response->status() . ' ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /** Cancel/void an order's authorisation (Retab: when we can't fulfill). */
    public function cancel(string $orderId, array $payload): array
    {
        $response = $this->client()->post("/orders/{$orderId}/cancel", $payload);

        // 409 = already cancelled / not cancellable; treat as a no-op.
        if (! $response->successful() && $response->status() !== 409) {
            throw new RuntimeException("Tamara cancel {$orderId} failed: " . $response->status() . ' ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Verify the HS256 `tamara-token` JWT from a webhook against the
     * notification token. Constant-time signature comparison; also rejects
     * expired tokens. Returns false on any malformed input.
     */
    public function verifyNotificationToken(?string $jwt): bool
    {
        if ($this->notificationToken === '' || ! $jwt) {
            return false;
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }

        [$header64, $payload64, $signature64] = $parts;

        $header = json_decode($this->base64UrlDecode($header64), true);
        if (! is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return false;
        }

        $expected = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header64}.{$payload64}", $this->notificationToken, true)
        );

        if (! hash_equals($expected, $signature64)) {
            return false;
        }

        $payload = json_decode($this->base64UrlDecode($payload64), true);
        if (is_array($payload) && isset($payload['exp']) && time() >= (int) $payload['exp']) {
            return false;
        }

        return true;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
