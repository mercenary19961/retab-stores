<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Live Meta WhatsApp Cloud API transport. POSTs to /{phone_number_id}/messages
 * with a Bearer access token. Returns the wam_id from messages[0].id.
 */
class CloudApiGateway implements WhatsAppGateway
{
    public function __construct(
        protected string $token,
        protected string $phoneNumberId,
        protected string $baseUrl,
    ) {}

    public function sendTemplate(string $to, string $template, string $language, array $params = []): string
    {
        $components = [];
        if ($params !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], array_values($params)),
            ];
        }

        return $this->sendTemplatePayload($to, $template, $language, $components);
    }

    /**
     * 🔴 Meta REJECTS an authentication template sent like any other template.
     * Its copy-code button carries the code as well, so the code has to be passed
     * twice: once for the body and once as the button's parameter. Sending the
     * body alone (what `sendTemplate` does) fails every login code.
     * https://developers.facebook.com/docs/whatsapp/business-management-api/authentication-templates/copy-code-button-authentication-templates
     */
    public function sendAuthenticationCode(string $to, string $template, string $language, string $code): string
    {
        return $this->sendTemplatePayload($to, $template, $language, [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
        ]);
    }

    public function sendText(string $to, string $body): string
    {
        return $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }

    /**
     * ⚠️ Being BOUND is not the same as being usable: `WHATSAPP_DRIVER=cloud` with a
     * blank token or phone-number id constructs this class perfectly happily and then
     * 401s on the first send. Checking the credentials here means a half-finished
     * configuration is reported as "not live" rather than as a mystery failure at the
     * moment a customer is trying to sign in.
     */
    public function isLive(): bool
    {
        return $this->token !== '' && $this->phoneNumberId !== '';
    }

    /**
     * Non-throwing readiness probe for `integrations:check`: reads the phone
     * number itself, which proves the token is valid AND can see this number.
     *
     * @return array{configured: bool, ok: bool, status: int|null, message: string}
     */
    public function ping(): array
    {
        if (! $this->isLive()) {
            return ['configured' => false, 'ok' => false, 'status' => null, 'message' => 'token or phone number id missing'];
        }

        try {
            $response = $this->readClient()->get("/{$this->phoneNumberId}", [
                'fields' => 'display_phone_number,verified_name,quality_rating,code_verification_status',
            ]);
        } catch (Throwable $e) {
            return ['configured' => true, 'ok' => false, 'status' => null, 'message' => 'unreachable: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return [
                'configured' => true,
                'ok' => false,
                'status' => $response->status(),
                'message' => (string) ($response->json('error.message') ?? 'request rejected'),
            ];
        }

        return [
            'configured' => true,
            'ok' => true,
            'status' => $response->status(),
            'message' => sprintf(
                '%s (%s), quality %s',
                $response->json('verified_name') ?? '?',
                $response->json('display_phone_number') ?? '?',
                $response->json('quality_rating') ?? 'unknown',
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $components
     */
    private function sendTemplatePayload(string $to, string $template, string $language, array $components): string
    {
        return $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload): string
    {
        $response = $this->sendClient()->post("/{$this->phoneNumberId}/messages", $payload);

        if (! $response->successful()) {
            throw new RuntimeException('WhatsApp send failed: '.$response->status().' '.$response->body());
        }

        $wamId = $response->json('messages.0.id');

        if (! $wamId) {
            throw new RuntimeException('WhatsApp response missing message id.');
        }

        return (string) $wamId;
    }

    /**
     * Sends are NEVER retried here.
     *
     * 🔴 A lost response is indistinguishable from a lost request, so an
     * automatic retry after a message Meta had already accepted delivers it
     * twice: a customer gets two confirmations, a campaign is paid for twice.
     * Same class of bug as the Moyasar and Tamara refunds (2026-08-15/16). A
     * failure surfaces to the caller instead; the queued SendWhatsappMessage job
     * owns retrying, with backoff, and no-ops once its ledger row says `sent`.
     */
    private function sendClient(): PendingRequest
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->baseUrl($this->baseUrl);
    }

    /** Reads change nothing, so a transient fault is safe to retry. */
    private function readClient(): PendingRequest
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 200, fn (Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $this->transient($e->response)), throw: false)
            ->baseUrl($this->baseUrl);
    }

    private function transient(Response $response): bool
    {
        return $response->serverError() || $response->status() === 429;
    }
}
