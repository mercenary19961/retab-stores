<?php

namespace App\Services\Auth;

use App\Models\OtpVerification;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * WhatsApp one-time-code auth. The decided (and only) OTP channel is WhatsApp —
 * no SMS provider. Codes are 6 digits, stored HASHED (never plaintext), expire
 * quickly, are single-use, and cap verify attempts. A short resend cooldown stops
 * code-spamming a number.
 */
class OtpService
{
    public const CODE_TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        protected WhatsAppService $whatsapp,
    ) {}

    /**
     * Issue a code for the phone and send it over WhatsApp. Throws if a code was
     * requested too recently (cooldown).
     */
    public function request(string $phone, string $purpose = 'login'): void
    {
        $phone = $this->normalize($phone);

        $recent = OtpVerification::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if ($recent && $recent->created_at->gt(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
            throw new RuntimeException(__('messages.otp.rate_limited'));
        }

        $code = (string) random_int(100000, 999999);

        OtpVerification::create([
            'phone' => $phone,
            'code' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        $this->whatsapp->sendOtp($phone, $code);
    }

    /**
     * Verify a submitted code against the latest usable OTP. Single-use: a correct
     * code is consumed; a wrong one burns an attempt. Returns success.
     */
    public function verify(string $phone, string $code, string $purpose = 'login'): bool
    {
        $phone = $this->normalize($phone);

        $otp = OtpVerification::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp || ! $otp->isUsable() || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $otp->code)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }

    /**
     * E.164 digits, no '+'. Single source of truth so request() and verify() key
     * on the same value the WhatsApp layer sends to.
     */
    public function normalize(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
