<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Non-throwing readiness probe for the Resend mail transport, mirroring the
 * ping() on the payment/shipping clients so `integrations:check` can report mail
 * the same way. (Resend has no webhook we consume, so there's nothing to register.)
 *
 * Probes GET /domains, which cleanly separates the states we care about:
 *   200                    → full-access key; the message names each sending
 *                            domain and its verification status
 *   401 restricted_api_key → key is GENUINE but send-only. That's the *preferred*
 *                            production key type, so it counts as OK — it simply
 *                            can't enumerate domains
 *   400 validation_error   → the key is wrong
 */
class ResendProbe
{
    /**
     * @return array{configured: bool, ok: bool, status: int|null, message: string}
     */
    public function ping(): array
    {
        $key = (string) config('services.resend.key');

        if ($key === '') {
            return ['configured' => false, 'ok' => false, 'status' => null, 'message' => 'RESEND_KEY not set'];
        }

        try {
            $response = Http::withToken($key)->timeout(10)->get('https://api.resend.com/domains');
        } catch (\Throwable $e) {
            return ['configured' => true, 'ok' => false, 'status' => null, 'message' => 'could not reach api.resend.com: '.$e->getMessage()];
        }

        if ($response->successful()) {
            $domains = collect($response->json('data') ?? [])
                ->map(fn (array $d) => ($d['name'] ?? '?').' ['.($d['status'] ?? '?').']')
                ->all();

            return [
                'configured' => true,
                'ok' => true,
                'status' => $response->status(),
                'message' => $domains === []
                    ? 'key valid — but NO sending domain added yet (only onboarding@resend.dev will send)'
                    : 'key valid — domains: '.implode(', ', $domains),
            ];
        }

        if ($response->json('name') === 'restricted_api_key') {
            return [
                'configured' => true,
                'ok' => true,
                'status' => $response->status(),
                'message' => 'key valid (send-only key — cannot list domains, which is expected)',
            ];
        }

        return [
            'configured' => true,
            'ok' => false,
            'status' => $response->status(),
            'message' => (string) ($response->json('message') ?? 'unexpected response'),
        ];
    }
}
