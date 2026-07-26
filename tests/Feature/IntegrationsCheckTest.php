<?php

namespace Tests\Feature;

use App\Services\Payments\MoyasarGateway;
use App\Services\Payments\Tamara\TamaraClient;
use App\Services\Shipping\Oto\OtoClient;
use Illuminate\Support\Facades\Http;
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

    // ---- Command -------------------------------------------------------------

    public function test_command_runs_offline_without_network(): void
    {
        Http::fake();

        $this->artisan('integrations:check --offline')
            ->assertSuccessful();

        Http::assertNothingSent();
    }
}
