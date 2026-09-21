<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What counts as a phone number we could actually reach.
 *
 * 🔴 Before 2026-09-21 the answer was "any string of up to 20 characters", in
 * every controller in the app. `4343443434` — the number the client typed to
 * report the bug — sailed through checkout.
 */
class PhoneNumberValidityTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function numbers(): array
    {
        return [
            // --- the report ---------------------------------------------------
            'the reported number: 10 digits, no country code, not a mobile' => ['4343443434', false],

            // --- Saudi mobiles, as customers actually type them ---------------
            'local with leading zero' => ['0512345678', true],
            'local without leading zero' => ['512345678', true],
            'spaced' => ['050 123 4567', true],
            'dashed' => ['055-123-4567', true],
            'with country code' => ['+966512345678', true],
            'country code, no plus' => ['966512345678', true],
            'country code via 00' => ['00966512345678', true],
            // Every assigned Saudi mobile prefix is 5X; the second digit is not
            // constrained here on purpose, because a newly assigned range would
            // otherwise be rejected as a typo.
            'other operator prefix' => ['0591234567', true],

            // --- too short / too long ------------------------------------------
            'partial, as typed halfway' => ['05123', false],
            'one digit short' => ['051234567', false],
            'one digit long' => ['05123456789', false],
            'empty' => ['', false],
            'blank space' => ['   ', false],

            // --- not a mobile ---------------------------------------------------
            // 🔑 A Riyadh landline is a REAL number but not one we can WhatsApp or
            // send an OTP to, and both are load-bearing here.
            'riyadh landline' => ['0112898888', false],

            // --- genuinely international ----------------------------------------
            'uae with plus' => ['+971501234567', true],
            'kuwait with plus' => ['+96550123456', true],
            'uk with plus' => ['+447911123456', true],
            'international via 00' => ['00971501234567', true],
            // 🔑 The rule that makes the reported number fail: without a country
            // code a bare run of digits is a typo, not an international number.
            'bare foreign digits, no country code' => ['971501234567', false],

            // --- junk -------------------------------------------------------------
            'letters' => ['asdfghjkl', false],
            'punctuation only' => ['+++', false],
            'plus but far too short' => ['+9665', false],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_judges_a_number(string $input, bool $expected): void
    {
        $this->assertSame(
            $expected,
            PhoneNumber::isValid($input),
            sprintf('[%s] should be %s', $input, $expected ? 'accepted' : 'rejected'),
        );
    }

    public function test_null_is_not_a_phone_number(): void
    {
        $this->assertFalse(PhoneNumber::isValid(null));
    }

    /**
     * 🔑 The two halves of PhoneNumber must agree: anything we ACCEPT has to be
     * something toWhatsApp() can turn into a number Meta will take, or we would
     * be promising to reach a customer we cannot message.
     */
    public function test_every_accepted_saudi_number_normalises_for_whatsapp(): void
    {
        foreach (['0512345678', '512345678', '050 123 4567', '+966512345678', '00966512345678'] as $input) {
            $this->assertTrue(PhoneNumber::isValid($input), "{$input} should be valid");
            $this->assertMatchesRegularExpression(
                '/^9665\d{8}$/',
                (string) PhoneNumber::toWhatsApp($input),
                "{$input} should normalise to a Saudi WhatsApp number",
            );
        }
    }
}
