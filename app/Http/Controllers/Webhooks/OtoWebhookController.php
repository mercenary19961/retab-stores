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
        if (! $this->authorized($request)) {
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

    /**
     * Accept the shared secret from any of the three places OTO can put it.
     *
     * `Authorization` is OTO's own documented mechanism — the dashboard's
     * "Authorization Key" field is sent verbatim in that header — so it is the
     * one to prefer. The `?token=` query form is kept because it is what the
     * registered URL has always carried and needs no dashboard field, and it
     * survives if a proxy ever strips the header. Bearer/Token prefixes are
     * tolerated since the dashboard does not say whether it adds one.
     *
     * Not implemented: the optional HMAC-SHA256 signature OTO can derive from
     * the separate "Secret Key" field (`orderId:status:timestamp`). A shared
     * secret over TLS is adequate here, and the exact signing string should be
     * confirmed against a real callback before being enforced — getting it
     * wrong would reject every genuine delivery notification.
     */
    private function authorized(Request $request): bool
    {
        $header = $request->header('Authorization');

        $candidates = [
            $request->query('token'),
            $request->input('secret_token'),
            is_string($header) ? preg_replace('/^(Bearer|Token)\s+/i', '', trim($header)) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $this->gateway->verifyWebhookToken($candidate)) {
                return true;
            }
        }

        return false;
    }
}
