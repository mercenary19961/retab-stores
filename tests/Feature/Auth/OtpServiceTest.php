<?php

namespace Tests\Feature\Auth;

use App\Exceptions\WhatsAppUnavailableException;
use App\Models\OtpVerification;
use App\Models\WhatsappMessage;
use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OtpService
    {
        return app(OtpService::class);
    }

    public function test_request_refuses_when_whatsapp_cannot_deliver(): void
    {
        // No withLiveWhatsapp() here — the default log driver, i.e. exactly what a
        // deployment with no WHATSAPP_* variables runs.
        $this->expectException(WhatsAppUnavailableException::class);

        try {
            $this->service()->request('966500000000');
        } finally {
            // 🔑 The guard runs BEFORE the row is written, so no unusable code is
            // left behind for a customer to be asked for.
            $this->assertDatabaseCount('otp_verifications', 0);
            $this->assertDatabaseCount('whatsapp_messages', 0);
        }
    }

    public function test_request_stores_a_hashed_code_and_sends_a_redacted_whatsapp_message(): void
    {
        $this->withLiveWhatsapp();
        $this->service()->request('+966 50 111 2222');

        $otp = OtpVerification::firstOrFail();
        $this->assertSame('966501112222', $otp->phone);     // normalized
        $this->assertNotSame('', $otp->code);
        $this->assertTrue(str_starts_with($otp->code, '$'));  // hashed, not plaintext

        // OTP message logged but the code itself is redacted in the ledger.
        $this->assertDatabaseHas('whatsapp_messages', ['purpose' => 'otp', 'recipient' => '966501112222']);
        $message = WhatsappMessage::where('purpose', 'otp')->firstOrFail();
        $this->assertSame(['***'], $message->payload['params']);
    }

    public function test_request_enforces_resend_cooldown(): void
    {
        $this->withLiveWhatsapp();
        $this->service()->request('966500000000');

        $this->expectException(\RuntimeException::class);
        $this->service()->request('966500000000');
    }

    public function test_verify_succeeds_and_consumes_the_code(): void
    {
        $otp = OtpVerification::create([
            'phone' => '966500000000',
            'code' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->assertTrue($this->service()->verify('+966500000000', '123456'));
        $this->assertNotNull($otp->fresh()->consumed_at);
    }

    public function test_verify_fails_and_burns_an_attempt_on_wrong_code(): void
    {
        $otp = OtpVerification::create([
            'phone' => '966500000000',
            'code' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->assertFalse($this->service()->verify('966500000000', '000000'));
        $this->assertSame(1, $otp->fresh()->attempts);
        $this->assertNull($otp->fresh()->consumed_at);
    }

    public function test_verify_fails_on_expired_code(): void
    {
        OtpVerification::create([
            'phone' => '966500000000',
            'code' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertFalse($this->service()->verify('966500000000', '123456'));
    }
}
