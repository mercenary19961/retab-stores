<?php

namespace App\Console\Commands;

use App\Services\Payments\MoyasarGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\Tamara\TamaraClient;
use App\Services\Shipping\Oto\OtoClient;
use Illuminate\Console\Command;

/**
 * Readiness check for the external integrations (payments + shipping). Run this
 * whenever you (re)configure a provider — especially the moment the client's keys
 * land in the Railway env — to confirm each one is wired correctly before a real
 * checkout depends on it.
 *
 *   php artisan integrations:check            # env presence + live API probe
 *   php artisan integrations:check --offline  # env presence only (no network)
 *
 * For each provider it reports three things:
 *   1. Env      — are the required variables set? (which ones are missing)
 *   2. Webhook  — the exact URL to register in that provider's dashboard
 *                 (built from APP_URL, so APP_URL must be the real site URL)
 *   3. Live     — does the credential actually authenticate against the API?
 *
 * ── What each provider needs (see also .env.example) ─────────────────────────
 *   Moyasar (cards)  → MOYASAR_SECRET_KEY, MOYASAR_WEBHOOK_SECRET
 *                      dashboard.moyasar.com → Settings → API keys + Webhooks
 *   Tamara (BNPL)    → TAMARA_API_TOKEN, TAMARA_NOTIFICATION_TOKEN
 *                      (+ TAMARA_BASE_URL = https://api-sandbox.tamara.co to test)
 *   OTO / Tryoto     → OTO_REFRESH_TOKEN, OTO_WEBHOOK_SECRET, OTO_ORIGIN_CITY
 *                      tryoto.com dashboard → generate a refresh token
 *
 * Exit code is non-zero only when a *configured* provider fails its live probe,
 * so "not configured yet" never fails the command (that's the expected pre-launch
 * state). That also makes it safe to wire into a deploy smoke-check later.
 */
class CheckIntegrations extends Command
{
    protected $signature = 'integrations:check {--offline : Only check env presence; skip the live API probes}';

    protected $description = 'Report readiness of the payment + shipping integrations (config, webhook URLs, live auth)';

    public function handle(): int
    {
        $offline = (bool) $this->option('offline');

        $appUrl = (string) config('app.url');
        $this->newLine();
        $this->line("APP_URL: <comment>{$appUrl}</comment>");
        if (str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
            $this->warn('  APP_URL is local — the webhook/return URLs below will be wrong in production. Set it in Railway.');
        }
        $this->newLine();

        /**
         * Each provider: the env vars it needs (label => config key), the webhook
         * route to register, and a closure that runs its non-throwing ping().
         */
        $providers = [
            [
                'label' => 'Moyasar — cards (mada / Visa / Mastercard / Apple Pay / STC Pay)',
                'env' => [
                    'MOYASAR_SECRET_KEY' => 'services.moyasar.secret_key',
                    'MOYASAR_WEBHOOK_SECRET' => 'services.moyasar.webhook_secret',
                ],
                'webhook' => 'webhooks.moyasar',
                'probe' => function (): array {
                    $gateway = app(PaymentGateway::class);

                    return $gateway instanceof MoyasarGateway
                        ? $gateway->ping()
                        : ['configured' => false, 'ok' => false, 'status' => null, 'message' => 'active gateway is not Moyasar'];
                },
            ],
            [
                'label' => 'Tamara — installments (BNPL)',
                'env' => [
                    'TAMARA_API_TOKEN' => 'services.tamara.api_token',
                    'TAMARA_NOTIFICATION_TOKEN' => 'services.tamara.notification_token',
                ],
                'webhook' => 'webhooks.tamara',
                'probe' => fn (): array => app(TamaraClient::class)->ping(),
            ],
            [
                'label' => 'OTO / Tryoto — shipping',
                'env' => [
                    'OTO_REFRESH_TOKEN' => 'services.oto.refresh_token',
                    'OTO_WEBHOOK_SECRET' => 'services.oto.webhook_secret',
                ],
                'webhook' => 'webhooks.oto',
                'probe' => fn (): array => app(OtoClient::class)->ping(),
            ],
        ];

        $failures = 0;

        foreach ($providers as $provider) {
            $this->line("<options=bold>{$provider['label']}</>");

            foreach ($provider['env'] as $var => $configKey) {
                $set = filled(config($configKey));
                $this->line(sprintf('   %-24s %s', $var, $set ? '<info>set</info>' : '<error>MISSING</error>'));
            }

            $this->line(sprintf('   %-24s <comment>%s</comment>', 'webhook to register', route($provider['webhook'])));

            if ($offline) {
                $this->line(sprintf('   %-24s <comment>skipped (--offline)</comment>', 'live check'));
            } else {
                $result = ($provider['probe'])();
                [$tag, $text] = $this->formatProbe($result);
                $this->line(sprintf('   %-24s <%s>%s</%s>', 'live check', $tag, $text, $tag));

                if ($result['configured'] && ! $result['ok']) {
                    $failures++;
                }
            }

            $this->newLine();
        }

        if ($offline) {
            $this->info('Env presence only (--offline). Re-run without --offline to verify the credentials authenticate.');

            return self::SUCCESS;
        }

        if ($failures > 0) {
            $this->error("{$failures} configured integration(s) failed their live check — see above.");

            return self::FAILURE;
        }

        $this->info('No configured integration is failing. (Providers marked NOT CONFIGURED just need their keys.)');

        return self::SUCCESS;
    }

    /**
     * Map a ping() result to a [colour-tag, text] pair for the console.
     *
     * @param  array{configured: bool, ok: bool, status: int|null, message: string}  $result
     * @return array{0: string, 1: string}
     */
    private function formatProbe(array $result): array
    {
        $status = $result['status'] !== null ? " (HTTP {$result['status']})" : '';

        if (! $result['configured']) {
            return ['comment', 'NOT CONFIGURED — set the vars above, then re-run'];
        }

        if ($result['ok']) {
            return ['info', "OK — {$result['message']}{$status}"];
        }

        return ['error', "FAILED — {$result['message']}{$status}"];
    }
}
