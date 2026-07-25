<?php

namespace App\Support;

/**
 * Slugifier that PRESERVES Arabic letters (Laravel's Str::slug transliterates them
 * away). Produces clean, URL-safe slugs for an Arabic-first catalogue: Arabic
 * script + Arabic-Indic digits + lowercase Latin/ASCII digits, hyphen-separated.
 *
 * Arabic in a URL is valid — browsers/Google percent-encode it transparently and
 * display it decoded in the address bar and search results.
 */
class ArabicSlug
{
    public static function make(string $text): string
    {
        $text = trim($text);

        // Drop tatweel (ـ) and Arabic diacritics/harakat so slugs stay stable
        // regardless of vocalisation. Ranges: harakat 064B–0652, superscript alef 0670.
        $text = (string) preg_replace('/[\x{0640}\x{064B}-\x{0652}\x{0670}]/u', '', $text);

        // Lowercase any Latin (Arabic is caseless, so this only affects EN fragments).
        $text = mb_strtolower($text, 'UTF-8');

        // Anything that is NOT an Arabic-script char, ASCII letter, or ASCII digit
        // becomes a separator. \p{Arabic} covers Arabic letters + Arabic-Indic digits.
        $text = (string) preg_replace('/[^\p{Arabic}a-z0-9]+/u', '-', $text);

        // Collapse repeats and trim stray separators.
        $text = (string) preg_replace('/-+/', '-', $text);

        return trim($text, '-');
    }
}
