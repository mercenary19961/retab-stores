<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload): string
    {
        $response = $this->client()->post("/{$this->phoneNumberId}/messages", $payload);

        if (! $response->successful()) {
            throw new RuntimeException('WhatsApp send failed: ' . $response->status() . ' ' . $response->body());
        }

        $wamId = $response->json('messages.0.id');

        if (! $wamId) {
            throw new RuntimeException('WhatsApp response missing message id.');
        }

        return (string) $wamId;
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 200, throw: false)
            ->baseUrl($this->baseUrl);
    }
}
