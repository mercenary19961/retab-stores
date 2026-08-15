<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
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

    /**
     * Client for WRITES (invoice creation, refunds). Deliberately has NO retry.
     *
     * 🔴 A retried write can execute TWICE at Moyasar. This previously carried a
     * bare `retry(2, 200, throw: false)`, whose behaviour was measured rather than
     * assumed (one process per case, against the real client):
     *
     *   4xx (400/404/422)      1 request   — not retried
     *   429 and 5xx            2 requests  — retried
     *   lost connection        2 requests  — retried, AND RETURNS SUCCESS
     *
     * The last line is the dangerous one. A lost response is indistinguishable
     * from a lost request, so a refund that Moyasar had already executed was
     * issued a second time and this method returned normally — one row in our
     * `payments` ledger, two refunds in the merchant account, and nothing in our
     * books to show it. A 5xx is the same ambiguity with a status code attached.
     *
     * Retab issues partial refunds (a return with or without the shipping fee),
     * so there is usually refundable balance left for a duplicate to succeed
     * against. A full refund would more likely be rejected for exceeding the
     * remaining balance — but that is Moyasar's accounting saving us, not our
     * design.
     *
     * A failed write now surfaces to the caller instead: checkout flashes
     * `payment.init_failed` and the admin refund flow reports the error.
     */
    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->timeout(20)
            ->baseUrl($this->baseUrl);
    }

    /**
     * Client for READS (fetch payment/invoice, ping). Safe to repeat, so it rides
     * out a blip — but only on genuinely transient faults. Retrying a 4xx just
     * sends the same rejected request three times.
     */
    private function readClient(): PendingRequest
    {
        return $this->client()->retry(2, 200, fn (\Throwable $e) => $this->isTransient($e), throw: false);
    }

    private function isTransient(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        return $e instanceof RequestException
            && ($e->response->status() >= 500 || $e->response->status() === 429);
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
                'Moyasar invoice creation failed: '.$response->status().' '.$response->body()
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
        $response = $this->readClient()->get("/payments/{$paymentId}");

        if (! $response->successful()) {
            throw new RuntimeException("Moyasar fetch payment {$paymentId} failed: ".$response->status());
        }

        return $this->normalize($response->json());
    }

    public function fetchInvoice(string $invoiceId): array
    {
        $response = $this->readClient()->get("/invoices/{$invoiceId}");

        if (! $response->successful()) {
            throw new RuntimeException("Moyasar fetch invoice {$invoiceId} failed: ".$response->status());
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

    /**
     * Readiness probe for `integrations:check` — never throws. Confirms the secret
     * key is set and actually authenticates, via a harmless read (listing invoices).
     *
     * @return array{configured: bool, ok: bool, status: int|null, message: string}
     */
    public function ping(): array
    {
        if ($this->secretKey === '') {
            return ['configured' => false, 'ok' => false, 'status' => null, 'message' => 'MOYASAR_SECRET_KEY not set'];
        }

        try {
            $response = $this->readClient()->get('/invoices');

            if ($response->successful()) {
                return ['configured' => true, 'ok' => true, 'status' => $response->status(), 'message' => 'authenticated'];
            }

            if (in_array($response->status(), [401, 403], true)) {
                return ['configured' => true, 'ok' => false, 'status' => $response->status(), 'message' => 'secret key rejected'];
            }

            return ['configured' => true, 'ok' => false, 'status' => $response->status(), 'message' => 'unexpected response'];
        } catch (\Throwable $e) {
            return ['configured' => true, 'ok' => false, 'status' => null, 'message' => 'unreachable: '.$e->getMessage()];
        }
    }

    public function refundPayment(string $paymentId, int $amount): NormalizedPayment
    {
        $response = $this->client()->post("/payments/{$paymentId}/refund", [
            'amount' => $amount,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Moyasar refund {$paymentId} failed: ".$response->status().' '.$response->body()
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

        return $url.$separator.'token='.urlencode($this->webhookToken);
    }
}
