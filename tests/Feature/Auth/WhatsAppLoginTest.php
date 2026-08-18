<?php

namespace Tests\Feature\Auth;

use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WhatsAppLoginTest extends TestCase
{
    use RefreshDatabase;

    private function seedOtp(string $phone, string $code = '123456'): void
    {
        OtpVerification::create([
            'phone' => $phone,
            'code' => Hash::make($code),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    public function test_send_creates_an_otp(): void
    {
        $this->withLiveWhatsapp();

        $this->post('/login/whatsapp/send', ['phone' => '+966500000000'])
            ->assertRedirect();

        $this->assertDatabaseHas('otp_verifications', ['phone' => '966500000000']);
    }

    /**
     * 🔴 The regression this whole change exists for. With no WHATSAPP_* variables the
     * transport is the log driver, which reports every send as a SUCCESS — so the
     * request returned 303, the page advanced to its code step, and the customer sat
     * waiting for a six-digit code that had been written to the server log. No error
     * anywhere, on the store's primary sign-in method.
     */
    public function test_send_refuses_when_whatsapp_cannot_deliver(): void
    {
        $response = $this->post('/login/whatsapp/send', ['phone' => '+966500000000']);

        // 🔑 Keyed `whatsapp`, NOT `phone`. The channel is down; their number is fine,
        // and flagging the field would send them off correcting a valid phone number.
        $response->assertSessionHasErrors('whatsapp');
        $response->assertSessionDoesntHaveErrors('phone');

        // Nothing issued, so nothing to be asked for later.
        $this->assertDatabaseCount('otp_verifications', 0);
    }

    public function test_the_sign_in_page_reports_whether_whatsapp_can_deliver(): void
    {
        // Default (log driver): the page renders its "use email instead" state.
        $this->get('/login/whatsapp')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('whatsappAuth', false));

        $this->withLiveWhatsapp();

        $this->get('/login/whatsapp')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('whatsappAuth', true));
    }

    public function test_verify_creates_a_new_user_and_logs_in(): void
    {
        $this->seedOtp('966500000000');

        $this->post('/login/whatsapp/verify', ['phone' => '+966500000000', 'code' => '123456'])
            ->assertRedirect(route('account.dashboard'));

        $this->assertAuthenticated();
        $user = User::where('phone', '966500000000')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertNotNull($user->phone_verified_at);
    }

    public function test_verify_logs_into_an_existing_user(): void
    {
        $existing = User::factory()->create(['phone' => '966500000000', 'role' => 'customer']);
        $this->seedOtp('966500000000');

        $this->post('/login/whatsapp/verify', ['phone' => '966500000000', 'code' => '123456']);

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::where('phone', '966500000000')->count()); // no duplicate
    }

    public function test_verify_rejects_a_wrong_code(): void
    {
        $this->seedOtp('966500000000');

        $this->post('/login/whatsapp/verify', ['phone' => '966500000000', 'code' => '999999'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }
}
