<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Moyasar implementation of the PaymentGateway contract.
 *
 * Moyasar covers mada, Visa/Mastercard, Apple Pay and STC Pay through a single
 * hosted Invoice, which is the lowest-PCI-scope way to take payments. Amounts
 * are always in halalas (1 SAR = 100 halalas). Auth is HTTP Basic with the
 * secret key as the username and an empty password. Cards capture immediately.
 */
class MoyasarGateway implements PaymentGateway
{
    public function __construct(
        protected string $secretKey,
        protected string $baseUrl,
        protected string $currency,
        protected string $webhookToken,
        protected string $successUrl,
        protected string $callbackUrl,
    ) {}

    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 200, throw: false)
            ->baseUrl($this->baseUrl);
    }

    public function createInvoice(Order $order): array
    {
        $amount = $this->toMinorUnits((float) $order->total);

        // The shared secret travels on the callback URL so we can authenticate
        // the server-to-server notification without relying on dashboard config.
        $callbackUrl = $this->appendToken($this->callbackUrl);

        $response = $this->client()->post('/invoices', [
            'amount' => $amount,
            'currency' => $this->currency,
            'description' => "Order {$order->order_number}",
            'callback_url' => $callbackUrl,
            'success_url' => $this->successUrl,
            'back_url' => $this->successUrl,
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_number' => (string) $order->order_number,
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Moyasar invoice creation failed: ' . $response->status() . ' ' . $response->body()
            );
        }

        $data = $response->json();

        if (empty($data['id']) || empty($data['url'])) {
            throw new RuntimeException('Moyasar invoice response missing id/url.');
        }

        return [
            'url' => $data['url'],
            'invoice_id' => $data['id'],
            'raw' => $data,
        ];
    }

    public function fetchPayment(string $paymentId): NormalizedPayment
    {
        $response = $this->client()->get("/payments/{$paymentId}");

        if (! $response->successful()) {
            throw new RuntimeException("Moyasar fetch payment {$paymentId} failed: " . $response->status());
        }

        return $this->normalize($response->json());
    }

    public function fetchInvoice(string $invoiceId): array
    {
        $response = $this->client()->get("/invoices/{$invoiceId}");

        if (! $response->successful()) {
            throw new RuntimeException("Moyasar fetch invoice {$invoiceId} failed: " . $response->status());
        }

        $data = $response->json();
        $payments = array_map(
            fn (array $p) => $this->normalize($p),
            $data['payments'] ?? []
        );

        return [
            'status' => $data['status'] ?? 'initiated',
            'payments' => $payments,
            'raw' => $data,
        ];
    }

    public function verifyWebhookToken(?string $token): bool
    {
        if ($this->webhookToken === '' || $token === null) {
            return false;
        }

        return hash_equals($this->webhookToken, $token);
    }

    public function refundPayment(string $paymentId, int $amount): NormalizedPayment
    {
        $response = $this->client()->post("/payments/{$paymentId}/refund", [
            'amount' => $amount,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Moyasar refund {$paymentId} failed: " . $response->status() . ' ' . $response->body()
            );
        }

        return $this->normalize($response->json());
    }

    /**
     * Map a Moyasar payment object into our provider-agnostic shape.
     */
    private function normalize(array $p): NormalizedPayment
    {
        $source = $p['source'] ?? [];
        $orderId = $p['metadata']['order_id'] ?? null;

        return new NormalizedPayment(
            id: (string) ($p['id'] ?? ''),
            status: (string) ($p['status'] ?? 'pending'),
            amount: (int) ($p['amount'] ?? 0),
            currency: (string) ($p['currency'] ?? $this->currency),
            sourceType: $source['type'] ?? null,
            sourceCompany: $source['company'] ?? null,
            invoiceId: $p['invoice_id'] ?? null,
            orderId: $orderId !== null ? (int) $orderId : null,
            failureMessage: $source['message'] ?? null,
            raw: $p,
        );
    }

    public function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function appendToken(string $url): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'token=' . urlencode($this->webhookToken);
    }
}
