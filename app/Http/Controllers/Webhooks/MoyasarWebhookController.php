<?php

namespace App\Http\Controllers\Webhooks;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Authoritative server-to-server payment notification endpoint.
 *
 * Security model (defense in depth):
 *   1. Shared secret token on the URL (?token=) or body (secret_token), compared
 *      in constant time.
 *   2. We never trust the posted amount/status — PaymentService RE-FETCHES the
 *      payment from Moyasar and verifies amount + currency before fulfilling.
 *   3. Idempotent: duplicate deliveries resolve to the same final state.
 *
 * CSRF-exempt via the webhooks/* rule in bootstrap/app.php.
 */
class MoyasarWebhookController
{
    public function __construct(
        protected PaymentService $paymentService,
        protected PaymentGateway $gateway,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $token = $request->query('token') ?? $request->input('secret_token');

        if (! $this->gateway->verifyWebhookToken(is_string($token) ? $token : null)) {
            Log::warning('Rejected Moyasar webhook: bad token', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Moyasar nests the payment under `data` for webhooks; the invoice
        // callback may post ids at the top level.
        $paymentId = $request->input('data.id')
            ?? $request->input('id')
            ?? $request->input('data.payments.0.id');

        if (! $paymentId) {
            Log::warning('Moyasar webhook had no resolvable payment id', ['type' => $request->input('type')]);

            // 200 so Moyasar stops retrying a payload we can't act on.
            return response()->json(['message' => 'No payment id'], 200);
        }

        try {
            $order = $this->paymentService->confirmFromGateway((string) $paymentId);
        } catch (\Throwable $e) {
            Log::error('Moyasar webhook processing error', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            // 500 => Moyasar retries (good for a transient re-fetch failure).
            return response()->json(['message' => 'Processing error'], 500);
        }

        return response()->json([
            'message' => 'ok',
            'order' => $order?->order_number,
            'status' => $order?->payment_status?->value,
        ], 200);
    }
}
