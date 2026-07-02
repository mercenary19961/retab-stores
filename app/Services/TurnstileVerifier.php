<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile server-side verification (ported from Sky Amman).
 * No-ops (returns true) while no secret is configured, so dev/staging work
 * before keys are provisioned; fails CLOSED on Cloudflare outages.
 */
class TurnstileVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        $secret = config('services.turnstile.secret_key');

        if (empty($secret)) {
            return true;
        }

        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::ENDPOINT, array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]));
        } catch (ConnectionException $e) {
            Log::warning('Turnstile siteverify connection failed', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Turnstile siteverify non-2xx', ['status' => $response->status()]);

            return false;
        }

        $success = $response->json('success') === true;

        if (! $success) {
            Log::warning('Turnstile siteverify rejected', [
                'error_codes' => $response->json('error-codes'),
                'hostname' => $response->json('hostname'),
            ]);
        }

        return $success;
    }

    public function isEnabled(): bool
    {
        return ! empty(config('services.turnstile.secret_key'));
    }
}
