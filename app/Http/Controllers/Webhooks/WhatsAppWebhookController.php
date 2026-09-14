<?php

namespace App\Http\Controllers\Webhooks;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Meta WhatsApp Cloud API webhook.
 *
 *  - GET  = the one-time subscription handshake (echo hub.challenge when the
 *           hub.verify_token matches our configured token).
 *  - POST = delivery/read status receipts (and inbound messages later for OTP).
 *           Authenticity is proven by the X-Hub-Signature-256 HMAC over the raw
 *           body, keyed with the Meta app secret.
 *
 * CSRF-exempt via the webhooks/* rule in bootstrap/app.php.
 */
class WhatsAppWebhookController
{
    public function __construct(
        protected WhatsAppService $service,
    ) {}

    public function verify(Request $request): Response
    {
        $verifyToken = (string) config('services.whatsapp.verify_token');

        if (
            $request->query('hub_mode') === 'subscribe'
            && $verifyToken !== ''
            && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))
        ) {
            return response((string) $request->query('hub_challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): JsonResponse
    {
        if (! $this->signatureValid($request)) {
            Log::warning('Rejected WhatsApp webhook: bad signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // entry[].changes[].value.statuses[] = delivery receipts.
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['statuses'] ?? []) as $status) {
                    if (! empty($status['id']) && ! empty($status['status'])) {
                        $this->service->updateStatusFromWebhook((string) $status['id'], (string) $status['status']);
                    }
                }
            }
        }

        return response()->json(['message' => 'ok'], 200);
    }

    /**
     * Verify X-Hub-Signature-256 against the raw request body.
     *
     * With no app secret configured there is nothing to verify against: that is
     * accepted in dev and tests, but 🔴 REFUSED in production. Otherwise a
     * forgotten WHATSAPP_APP_SECRET would let anyone who finds the URL post fake
     * delivery receipts and rewrite the message ledger, with nothing failing to
     * say so.
     */
    private function signatureValid(Request $request): bool
    {
        $secret = (string) config('services.whatsapp.app_secret');
        if ($secret === '') {
            return ! app()->environment('production');
        }

        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return $signature !== '' && hash_equals($expected, $signature);
    }
}
