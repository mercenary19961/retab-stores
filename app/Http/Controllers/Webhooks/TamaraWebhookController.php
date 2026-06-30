<?php

namespace App\Http\Controllers\Webhooks;

use App\Services\Payments\Tamara\TamaraClient;
use App\Services\Payments\Tamara\TamaraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Tamara webhook. Authenticity is proven by an HS256 JWT in the `tamara-token`
 * header (verified against the notification token). As with every gateway, the
 * webhook only triggers — TamaraService re-fetches and verifies the order.
 *
 * CSRF-exempt via the webhooks/* rule in bootstrap/app.php.
 */
class TamaraWebhookController
{
    public function __construct(
        protected TamaraService $service,
        protected TamaraClient $client,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->client->verifyNotificationToken($request->header('tamara-token'))) {
            Log::warning('Rejected Tamara webhook: bad token', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $tamaraOrderId = $request->input('order_id') ?? $request->input('data.order_id');

        if (! $tamaraOrderId) {
            return response()->json(['message' => 'No order_id'], 200);
        }

        try {
            $order = $this->service->confirm((string) $tamaraOrderId);
        } catch (\Throwable $e) {
            Log::error('Tamara webhook processing error', [
                'tamara_order_id' => $tamaraOrderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Processing error'], 500);
        }

        return response()->json([
            'message' => 'ok',
            'order' => $order?->order_number,
            'status' => $order?->payment_status?->value,
        ], 200);
    }
}
