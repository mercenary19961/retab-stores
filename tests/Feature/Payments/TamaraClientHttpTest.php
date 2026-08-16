<?php

namespace Tests\Feature\Payments;

use App\Services\Payments\Tamara\TamaraClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises the REAL TamaraClient over a faked HTTP layer.
 *
 * TamaraPaymentTest covers the order lifecycle through an anonymous subclass
 * that overrides every method, so it never touches this class's HTTP client —
 * exactly the blind spot that hid the same retry bug in MoyasarGateway. These
 * tests pin the wire behaviour instead.
 */
class TamaraClientHttpTest extends TestCase
{
    private function client(): TamaraClient
    {
        return new TamaraClient('api-token', 'notification-token', 'https://api-sandbox.tamara.co');
    }

    private function money(float $amount): array
    {
        return ['amount' => $amount, 'currency' => 'SAR'];
    }

    /**
     * 🔴 The worst case, and the reason the retry policy changed.
     *
     * Under the previous bare `retry(2, 200, throw: false)` this sent the refund
     * TWICE. A lost response is indistinguishable from a lost request, so the
     * second attempt can refund money Tamara has already given back — one row in
     * our ledger, two movements on the customer's plan.
     */
    public function test_a_refund_is_not_re_sent_after_a_lost_connection(): void
    {
        // ⚠️ The second entry is a SUCCESS on purpose. If a retry happens the
        // call returns normally, so this pins both the request count AND the
        // silent-success outcome the old policy produced.
        Http::fake(['*' => Http::sequence()->pushFailedConnection('cURL error 28: Operation timed out')->push(['status' => 'refunded'], 200)]);

        $this->expectException(ConnectionException::class);

        try {
            $this->client()->refund('tamara-order-1', ['total_amount' => $this->money(50)]);
        } finally {
            Http::assertSentCount(1);
        }
    }

    /** Taking the money twice is the mirror image of refunding it twice. */
    public function test_a_capture_is_not_re_sent_after_a_lost_connection(): void
    {
        Http::fake(['*' => Http::sequence()->pushFailedConnection('cURL error 28: Operation timed out')->push(['status' => 'fully_captured'], 200)]);

        $this->expectException(ConnectionException::class);

        try {
            $this->client()->capture('tamara-order-1', ['total_amount' => $this->money(50)]);
        } finally {
            Http::assertSentCount(1);
        }
    }

    /**
     * A 5xx is the same ambiguity with a status code attached: Tamara may well
     * have executed the write before failing to tell us about it.
     */
    public function test_a_refund_is_not_re_sent_after_a_server_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        try {
            $this->client()->refund('tamara-order-1', ['total_amount' => $this->money(50)]);
        } catch (\Throwable) {
        }

        Http::assertSentCount(1);
    }

    /**
     * Checkout creation is a write too — a retry leaves a stray unpaid session
     * behind, which pollutes reconciliation even though no money moves.
     */
    public function test_checkout_creation_is_not_re_sent(): void
    {
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        try {
            $this->client()->createCheckout(['order_reference_id' => 'RTB-1']);
        } catch (\Throwable) {
        }

        Http::assertSentCount(1);
    }

    /** Cancel releases a hold, and releasing it twice is still a duplicate write. */
    public function test_cancel_is_not_re_sent(): void
    {
        Http::fake(['*' => Http::sequence()->pushFailedConnection('cURL error 28: Operation timed out')->push(['status' => 'canceled'], 200)]);

        $this->expectException(ConnectionException::class);

        try {
            $this->client()->cancel('tamara-order-1', ['total_amount' => $this->money(50)]);
        } finally {
            Http::assertSentCount(1);
        }
    }

    /** Reads are safe to repeat, so a transient fault still gets a second go. */
    public function test_reads_are_retried_on_a_server_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        try {
            $this->client()->getOrder('tamara-order-1');
        } catch (\Throwable) {
        }

        Http::assertSentCount(2);
    }

    /**
     * ⚠️ But NOT on a 4xx. The previous config retried every unsuccessful
     * response including 400s, which can only ever fail identically the second
     * time.
     */
    public function test_reads_are_not_retried_on_a_client_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'not found'], 404)]);

        try {
            $this->client()->getOrder('tamara-order-1');
        } catch (\Throwable) {
        }

        Http::assertSentCount(1);
    }

    /** The webhook signature check is the only thing standing between us and a forged order event. */
    public function test_a_notification_token_is_verified_against_the_signing_secret(): void
    {
        $secret = 'notification-token';
        $encode = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

        $header = $encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = $encode(['exp' => time() + 600]);
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true)), '+/', '-_'), '=');

        $client = $this->client();

        $this->assertTrue($client->verifyNotificationToken("{$header}.{$payload}.{$signature}"));
        $this->assertFalse($client->verifyNotificationToken("{$header}.{$payload}.tampered"));
        $this->assertFalse($client->verifyNotificationToken(null));

        // An expired token is a replay, even with a valid signature.
        $stale = $encode(['exp' => time() - 10]);
        $staleSig = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$stale}", $secret, true)), '+/', '-_'), '=');
        $this->assertFalse($client->verifyNotificationToken("{$header}.{$stale}.{$staleSig}"));
    }
}
