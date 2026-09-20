<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /** @return array<string, array{?string, ?string}> */
    public static function numbers(): array
    {
        return [
            'local Saudi mobile' => ['0512345678', '966512345678'],
            'local with spaces' => ['05 1234 5678', '966512345678'],
            'without the leading zero' => ['512345678', '966512345678'],
            'already international' => ['+966 55 088 3845', '966550883845'],
            '00 prefix' => ['00966512345678', '966512345678'],
            // Another GCC country is left as typed rather than guessed at.
            'Kuwaiti international' => ['+965 5123 4567', '96551234567'],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_produces_whatsapp_digits(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toWhatsApp($input));
    }

    public function test_it_builds_a_click_to_chat_link(): void
    {
        $this->assertSame('https://wa.me/966512345678', PhoneNumber::whatsAppUrl('0512345678'));
        $this->assertNull(PhoneNumber::whatsAppUrl(null));
    }
}
