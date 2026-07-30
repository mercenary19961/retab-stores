<?php

namespace Tests\Feature;

use App\Services\Payments\MoyasarGateway;
use App\Services\Payments\Tamara\TamaraClient;
use App\Services\Shipping\Oto\OtoClient;
use App\Support\ResendProbe;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IntegrationsCheckTest extends TestCase
{
    private function moyasar(string $key = 'sk_test'): MoyasarGateway
    {
        return new MoyasarGateway($key, 'https://api.moyasar.com/v1', 'SAR', 'wh', 'https://x/success', 'https://x/webhooks/moyasar');
    }

    // ---- Moyasar -------------------------------------------------------------

    public function test_moyasar_ping_reports_ok_when_authenticated(): void
    {
        Http::fake(['api.moyasar.com/*' => Http::response(['invoices' => []], 200)]);

        $result = $this->moyasar()->ping();

        $this->assertTrue($result['configured']);
        $this->assertTrue($result['ok']);
    }

    public function test_moyasar_ping_reports_rejected_on_401(): void
    {
        Http::fake(['api.moyasar.com/*' => Http::response(['message' => 'unauthorized'], 401)]);

        $result = $this->moyasar()->ping();

        $this->assertTrue($result['configured']);
        $this->assertFalse($result['ok']);
        $this->assertSame(401, $result['status']);
    }

    public function test_moyasar_ping_reports_not_configured_without_a_key(): void
    {
        Http::fake();

        $result = $this->moyasar('')->ping();

        $this->assertFalse($result['configured']);
        Http::assertNothingSent();
    }

    // ---- Tamara --------------------------------------------------------------

    public function test_tamara_ping_treats_404_as_authenticated(): void
    {
        // A valid token reading a bogus order gets 404 (not found), not 401.
        Http::fake(['api.tamara.co/*' => Http::response(['message' => 'not found'], 404)]);

        $result = (new TamaraClient('token', 'notif', 'https://api.tamara.co'))->ping();

        $this->assertTrue($result['ok']);
    }

    public function test_tamara_ping_reports_rejected_on_401(): void
    {
        Http::fake(['api.tamara.co/*' => Http::response([], 401)]);

        $result = (new TamaraClient('token', 'notif', 'https://api.tamara.co'))->ping();

        $this->assertFalse($result['ok']);
        $this->assertSame(401, $result['status']);
    }

    // ---- OTO -----------------------------------------------------------------

    public function test_oto_ping_ok_when_token_exchange_succeeds(): void
    {
        Http::fake(['api.tryoto.com/*' => Http::response(['access_token' => 'abc'], 200)]);

        $result = (new OtoClient('refresh', 'https://api.tryoto.com/rest/v2'))->ping();

        $this->assertTrue($result['ok']);
    }

    public function test_oto_ping_rejected_when_refresh_token_bad(): void
    {
        Http::fake(['api.tryoto.com/*' => Http::response(['error' => 'invalid'], 401)]);

        $result = (new OtoClient('refresh', 'https://api.tryoto.com/rest/v2'))->ping();

        $this->assertFalse($result['ok']);
        $this->assertSame(401, $result['status']);
    }

    // ---- Resend (email) ------------------------------------------------------

    public function test_resend_ping_lists_domains_with_a_full_access_key(): void
    {
        config()->set('services.resend.key', 're_full');
        Http::fake(['api.resend.com/*' => Http::response(['data' => [['name' => 'retabstore.com', 'status' => 'verified']]], 200)]);

        $result = (new ResendProbe)->ping();

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('retabstore.com [verified]', $result['message']);
    }

    public function test_resend_ping_warns_when_no_sending_domain_exists(): void
    {
        config()->set('services.resend.key', 're_full');
        Http::fake(['api.resend.com/*' => Http::response(['data' => []], 200)]);

        $result = (new ResendProbe)->ping();

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('NO sending domain', $result['message']);
    }

    public function test_resend_ping_treats_a_send_only_key_as_ok(): void
    {
        // A restricted (send-only) key CANNOT read /domains — Resend answers 401
        // `restricted_api_key`. That still proves the key is genuine, and it's the
        // key type we actually want in production, so it must not read as a failure.
        config()->set('services.resend.key', 're_send_only');
        Http::fake(['api.resend.com/*' => Http::response([
            'statusCode' => 401,
            'message' => 'This API key is restricted to only send emails',
            'name' => 'restricted_api_key',
        ], 401)]);

        $result = (new ResendProbe)->ping();

        $this->assertTrue($result['configured']);
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('send-only', $result['message']);
    }

    public function test_resend_ping_reports_an_invalid_key(): void
    {
        config()->set('services.resend.key', 're_bogus');
        Http::fake(['api.resend.com/*' => Http::response([
            'statusCode' => 400,
            'message' => 'API key is invalid',
            'name' => 'validation_error',
        ], 400)]);

        $result = (new ResendProbe)->ping();

        $this->assertTrue($result['configured']);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('invalid', $result['message']);
    }

    public function test_resend_ping_reports_not_configured_without_a_key(): void
    {
        config()->set('services.resend.key', null);
        Http::fake();

        $result = (new ResendProbe)->ping();

        $this->assertFalse($result['configured']);
        Http::assertNothingSent();
    }

    // ---- mail:test -----------------------------------------------------------

    public function test_mail_test_command_sends_through_the_named_mailer(): void
    {
        // `array` is the test env's transport, so this exercises the real send path.
        $this->artisan('mail:test staff@example.com --mailer=array --from=no-reply@retabstore.com')
            ->assertSuccessful();

        /** @var Mailer $mailer */
        $mailer = Mail::mailer('array');
        /** @var ArrayTransport $transport */
        $transport = $mailer->getSymfonyTransport();

        $sent = $transport->messages();
        $this->assertCount(1, $sent);

        $message = $sent->first()->getOriginalMessage();
        $this->assertStringContainsString('mail configuration test', $message->getSubject());
        $this->assertSame('staff@example.com', $message->getTo()[0]->getAddress());
        $this->assertSame('no-reply@retabstore.com', $message->getFrom()[0]->getAddress());
    }

    // ---- Command -------------------------------------------------------------

    public function test_command_runs_offline_without_network(): void
    {
        Http::fake();

        $this->artisan('integrations:check --offline')
            ->assertSuccessful();

        Http::assertNothingSent();
    }
}
