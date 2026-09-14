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

    /**
     * 🔴 A missing app secret must not mean "accept everything" in production:
     * anyone who found the URL could rewrite the message ledger with fake receipts.
     */
    public function test_production_refuses_receipts_when_no_app_secret_is_configured(): void
    {
        $this->app['env'] = 'production';
        config()->set('services.whatsapp.app_secret', '');
        $message = WhatsappMessage::create(['recipient' => '966500000000', 'template' => 'order_confirmed', 'status' => 'sent', 'wam_id' => 'wamid.P']);

        $this->postJson('/webhooks/whatsapp', ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.P', 'status' => 'read']]]]]]]])
            ->assertUnauthorized();

        $this->assertSame('sent', $message->fresh()->status);
    }

    public function test_a_correctly_signed_receipt_is_accepted(): void
    {
        config()->set('services.whatsapp.app_secret', 'app-secret');
        $message = WhatsappMessage::create(['recipient' => '966500000000', 'template' => 'order_confirmed', 'status' => 'sent', 'wam_id' => 'wamid.S']);
        $body = json_encode(['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.S', 'status' => 'delivered']]]]]]]]);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'app-secret'),
        ], $body)->assertOk();

        $this->assertSame('delivered', $message->fresh()->status);
    }
}
