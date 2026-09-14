<?php

namespace Tests\Feature\WhatsApp;

use App\Services\WhatsApp\CloudApiGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Exercises the REAL gateway over Http::fake(). The service tests drive a fake
 * gateway, so without this nothing checks what we actually send to Meta — the
 * same blind spot that hid the Moyasar/Tamara double-refund bugs.
 */
class CloudApiGatewayHttpTest extends TestCase
{
    private function gateway(): CloudApiGateway
    {
        return new CloudApiGateway('test-token', '123456', 'https://graph.facebook.com/v21.0');
    }

    private const SENT = ['messages' => [['id' => 'wamid.OK']]];

    /** 🔴 Meta rejects an authentication template without the code on its copy-code button. */
    public function test_a_login_code_is_sent_in_the_body_and_on_the_copy_code_button(): void
    {
        Http::fake(['*' => Http::response(self::SENT)]);

        $this->assertSame('wamid.OK', $this->gateway()->sendAuthenticationCode('966500000000', 'otp', 'ar', '482913'));

        Http::assertSent(function (Request $request) {
            $components = $request['template']['components'];

            return $request->url() === 'https://graph.facebook.com/v21.0/123456/messages'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $components[0] === ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => '482913']]]
                && $components[1] === ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => '482913']]];
        });
    }

    public function test_an_ordinary_template_sends_its_body_parameters_only(): void
    {
        Http::fake(['*' => Http::response(self::SENT)]);

        $this->gateway()->sendTemplate('966500000000', 'order_confirmed', 'ar', ['Zaid', 'RTB-1']);

        Http::assertSent(fn (Request $request) => count($request['template']['components']) === 1
            && $request['template']['components'][0]['parameters'][1]['text'] === 'RTB-1');
    }

    /**
     * 🔴 A dropped connection after Meta accepted the message must NOT be retried
     * here: that would deliver it twice. The queued job owns retrying.
     */
    public function test_a_dropped_connection_is_not_retried(): void
    {
        // The second entry is a SUCCESS on purpose: a retry would silently deliver
        // the message twice and return normally.
        Http::fake(['*' => Http::sequence()->pushFailedConnection()->push(self::SENT)]);

        try {
            $this->gateway()->sendTemplate('966500000000', 'order_confirmed', 'ar', ['Zaid']);
            $this->fail('A failed send must surface to the caller.');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(AssertionFailedError::class, $e);
        }

        Http::assertSentCount(1);
    }

    public function test_a_server_error_is_sent_once_and_surfaces(): void
    {
        Http::fake(['*' => Http::sequence()->push(['error' => ['message' => 'boom']], 500)->push(self::SENT)]);

        try {
            $this->gateway()->sendTemplate('966500000000', 'order_confirmed', 'ar', ['Zaid']);
            $this->fail('A failed send must surface to the caller.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('500', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_ping_reports_the_number_when_the_token_works(): void
    {
        Http::fake(['*' => Http::response([
            'verified_name' => 'Retab',
            'display_phone_number' => '+966 55 088 3845',
            'quality_rating' => 'GREEN',
        ])]);

        $result = $this->gateway()->ping();

        $this->assertTrue($result['configured']);
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Retab', $result['message']);
        $this->assertStringContainsString('GREEN', $result['message']);
    }

    public function test_ping_surfaces_metas_own_error_when_the_token_is_rejected(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 401)]);

        $result = $this->gateway()->ping();

        $this->assertTrue($result['configured']);
        $this->assertFalse($result['ok']);
        $this->assertSame(401, $result['status']);
        $this->assertSame('Invalid OAuth access token.', $result['message']);
    }

    public function test_ping_without_credentials_is_not_configured_rather_than_failed(): void
    {
        Http::fake();

        $result = (new CloudApiGateway('', '', 'https://graph.facebook.com/v21.0'))->ping();

        $this->assertFalse($result['configured']);
        Http::assertNothingSent();
    }
}
