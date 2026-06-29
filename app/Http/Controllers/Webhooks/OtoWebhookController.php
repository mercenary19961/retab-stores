<?php

namespace App\Http\Controllers\Webhooks;

use App\Services\Shipping\ShippingGateway;
use App\Services\Shipping\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives OTO shipment status callbacks and reflects them on the order
 * (shipped / delivered / cancelled). Authenticated by a shared secret on the
 * URL (?token=) or in the body. CSRF-exempt via the webhooks/* rule.
 */
class OtoWebhookController
{
    public function __construct(
        protected ShippingService $shipping,
        protected ShippingGateway $gateway,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $token = $request->query('token') ?? $request->input('secret_token');

        if (! $this->gateway->verifyWebhookToken(is_string($token) ? $token : null)) {
            Log::warning('Rejected OTO webhook: bad token', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $orderNumber = $request->input('orderId')
            ?? $request->input('order_id')
            ?? $request->input('data.orderId');
        $status = $request->input('status')
            ?? $request->input('shipmentStatus')
            ?? $request->input('data.status');

        if (! $orderNumber || ! $status) {
            return response()->json(['message' => 'Missing orderId/status'], 200);
        }

        try {
            $order = $this->shipping->applyStatusUpdate((string) $orderNumber, (string) $status);
        } catch (\Throwable $e) {
            Log::error('OTO webhook processing error', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Processing error'], 500);
        }

        return response()->json([
            'message' => 'ok',
            'order' => $order?->order_number,
            'status' => $order?->status?->value,
        ], 200);
    }
}
