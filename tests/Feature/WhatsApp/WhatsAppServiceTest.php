<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'RTB-' . uniqid(),
            'customer_name' => 'Zaid',
            'customer_phone' => '+966 50 000 0000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'subtotal' => 150,
            'total' => 175,
        ], $overrides));
    }

    public function test_sending_records_a_sent_ledger_row_with_normalized_recipient(): void
    {
        $order = $this->order();

        $message = app(WhatsAppService::class)->notifyOrderConfirmed($order);

        $this->assertNotNull($message);
        $this->assertSame('sent', $message->status);
        $this->assertSame('966500000000', $message->recipient); // normalized to E.164 digits
        $this->assertSame('order_confirm', $message->purpose);
        $this->assertNotNull($message->wam_id);
        $this->assertSame($order->id, $message->order_id);
    }

    public function test_no_recipient_sends_nothing(): void
    {
        $order = $this->order(['customer_phone' => '']);

        $this->assertNull(app(WhatsAppService::class)->notifyOrderConfirmed($order));
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_transport_failure_is_swallowed_and_logged_as_failed(): void
    {
        // Bind a gateway that always throws.
        $this->app->bind(WhatsAppGateway::class, fn () => new class implements WhatsAppGateway {
            public function sendTemplate(string $to, string $template, string $language, array $params = []): string
            {
                throw new \RuntimeException('network down');
            }

            public function sendText(string $to, string $body): string
            {
                throw new \RuntimeException('network down');
            }
        });

        $order = $this->order();
        $message = app(WhatsAppService::class)->notifyOrderConfirmed($order);

        $this->assertSame('failed', $message->status);
        $this->assertStringContainsString('network down', (string) $message->error);
    }

    public function test_admin_new_order_fans_out_to_configured_recipients(): void
    {
        config()->set('services.whatsapp.admin_recipients', '+966511111111, +966522222222');

        app(WhatsAppService::class)->notifyAdminsNewOrder($this->order());

        $this->assertDatabaseCount('whatsapp_messages', 2);
        $this->assertDatabaseHas('whatsapp_messages', ['recipient' => '966511111111', 'purpose' => 'admin_new_order']);
        $this->assertDatabaseHas('whatsapp_messages', ['recipient' => '966522222222', 'purpose' => 'admin_new_order']);
    }

    public function test_webhook_status_updates_the_ledger_row(): void
    {
        $message = WhatsappMessage::create([
            'recipient' => '966500000000',
            'template' => 'order_confirmed',
            'status' => 'sent',
            'wam_id' => 'wamid.ABC',
        ]);

        $updated = app(WhatsAppService::class)->updateStatusFromWebhook('wamid.ABC', 'delivered');

        $this->assertSame('delivered', $updated->status);
        $this->assertSame('delivered', $message->fresh()->status);
    }
}
