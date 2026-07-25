<?php

namespace Tests\Unit;

use App\Support\ArabicSlug;
use Tests\TestCase;

class ArabicSlugTest extends TestCase
{
    public function test_preserves_arabic_letters_and_hyphenates_spaces(): void
    {
        $this->assertSame('خلاص-أشيقر-درجة-أولى', ArabicSlug::make('خلاص أشيقر درجة أولى'));
    }

    public function test_keeps_ascii_and_arabic_indic_digits(): void
    {
        $this->assertSame('سكري-500-جرام', ArabicSlug::make('سكري 500 جرام'));
        $this->assertSame('سكري-١٥٠٠-كيلو', ArabicSlug::make('سكري ١٥٠٠ كيلو'));
    }

    public function test_lowercases_latin_fragments(): void
    {
        $this->assertSame('سكري-مفتل-vip', ArabicSlug::make('سكري مفتل VIP'));
    }

    public function test_strips_tatweel_and_diacritics(): void
    {
        // Tatweel (ـ) and harakat must not survive into the slug.
        $this->assertSame('خلاص', ArabicSlug::make('خـلاصْ'));
    }

    public function test_collapses_separators_and_trims_edges(): void
    {
        $this->assertSame('تمر-ذهبي', ArabicSlug::make('  تمر --- ذهبي,  '));
    }

    public function test_drops_punctuation_and_symbols(): void
    {
        $this->assertSame('دبس-التمر-عصار', ArabicSlug::make('دبس التمر (عصار)!'));
    }
}
