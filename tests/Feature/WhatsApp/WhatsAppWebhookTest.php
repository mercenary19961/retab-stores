<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_handshake_echoes_challenge_when_token_matches(): void
    {
        config()->set('services.whatsapp.verify_token', 'secret-token');

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=secret-token&hub_challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_verify_handshake_rejects_wrong_token(): void
    {
        config()->set('services.whatsapp.verify_token', 'secret-token');

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345')
            ->assertForbidden();
    }

    public function test_status_receipt_updates_message_status(): void
    {
        // No app_secret configured in tests → signature check is skipped.
        $message = WhatsappMessage::create([
            'recipient' => '966500000000',
            'template' => 'order_confirmed',
            'status' => 'sent',
            'wam_id' => 'wamid.XYZ',
        ]);

        $payload = [
            'entry' => [
                ['changes' => [
                    ['value' => ['statuses' => [
                        ['id' => 'wamid.XYZ', 'status' => 'read'],
                    ]]],
                ]],
            ],
        ];

        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();

        $this->assertSame('read', $message->fresh()->status);
    }
}
