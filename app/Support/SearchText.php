<?php

namespace App\Support;

/**
 * Text normalisation for catalogue search.
 *
 * 🔑 The single biggest win in Arabic search is NOT fuzzy matching — it is folding
 * the letters that a shopper and a data-entry clerk spell differently for the same
 * word. أ/إ/آ vs ا, ة vs ه, ى vs ي, with or without harakat, with or without a
 * kashida. Without this, someone typing «علبه» never finds «علبة» and «سكرى» never
 * finds «سكري», and no amount of typo tolerance rescues that because nothing about
 * it is a typo.
 *
 * ⚠️ THIS FUNCTION IS MIRRORED IN `resources/js/lib/search.ts`. They must produce
 * identical output: the server pre-normalises each product's `terms` into the search
 * index, and the client normalises only the QUERY before matching against it. A
 * divergence means the typeahead silently stops matching. Both sides are pinned by
 * the same table of cases — see `tests/Feature/SearchTest.php` and
 * `resources/js/lib/__tests__/search.test.ts`; change one, change both.
 *
 * ⚠️ Latin diacritics are deliberately NOT stripped (no NFD fold). JS could do it in
 * one call and PHP could not without ext-intl, so doing it on one side only would be
 * the exact divergence above. No product text contains an accented Latin character,
 * so the case cannot arise here.
 *
 * Related but separate: `ArabicSlug` also drops tatweel and harakat, but must NOT
 * fold أ→ا — a URL should keep the real letters.
 */
class SearchText
{
    /**
     * Words a shopper types that appear nowhere in the product text.
     *
     * Each group is interchangeable; a product matching any member gains all of
     * them. Deliberately small and hand-curated: every entry here is a term the
     * real catalogue actually needs. It is applied to the PRODUCT side at index
     * time, not to the query, so the client's fuzzy layer sees the expansions too
     * and there is only one list to keep.
     *
     * 🔑 Written unnormalised for readability and normalised on use, so «علبة» here
     * matches the «علبه» that normalisation produces.
     */
    private const SYNONYMS = [
        // The catalogue writes "box" three ways, one of them transliterated.
        ['بوكس', 'علبة', 'صندوق', 'box'],
        ['تمر', 'تمور', 'تمرة', 'dates', 'date'],
        ['كرتون', 'carton'],
        ['حبة', 'single', 'piece'],
        ['صينية', 'tray'],
        ['محشي', 'محشو', 'stuffed'],
        ['مكسرات', 'nuts'],
        ['لوز', 'almond', 'almonds'],
        ['فستق', 'pistachio'],
        ['كاجو', 'cashew'],
        ['شوكولاتة', 'chocolate'],
        ['قهوة', 'coffee'],
        ['عسل', 'honey'],
        ['سمن', 'ghee'],
        ['طحينة', 'tahini'],
        ['دبس', 'molasses'],
        ['هدية', 'هدايا', 'gift'],
        ['فاخر', 'فاخرة', 'premium', 'luxury'],
        // Units, so "250 جم" finds "250 جرام" and "1kg" finds "1 كيلو".
        ['جم', 'جرام', 'g', 'gram', 'grams'],
        ['كجم', 'كيلو', 'كيلوجرام', 'kg'],
    ];

    /**
     * Fold a string to its searchable form: lowercase, no harakat or kashida,
     * unified alef/ya/ta-marbuta/hamza, ASCII digits, single-spaced words.
     */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        // Kashida (0640), harakat (064B–0652) and the superscript alef (0670) are
        // decoration: they change how a word is voiced, never which word it is.
        $text = (string) preg_replace('/[\x{0640}\x{064B}-\x{0652}\x{0670}]/u', '', $text);

        $text = strtr($text, [
            // Alef with any hamza or madda, plus the ornate alef.
            "\u{0623}" => "\u{0627}", "\u{0625}" => "\u{0627}", "\u{0622}" => "\u{0627}", "\u{0671}" => "\u{0627}",
            "\u{0649}" => "\u{064A}", // alef maqsura → ya
            "\u{0629}" => "\u{0647}", // ta marbuta → ha
            "\u{0624}" => "\u{0648}", // waw with hamza → waw
            "\u{0626}" => "\u{064A}", // ya with hamza → ya
            "\u{0621}" => '',         // bare hamza carries no sound of its own here
        ]);

        // Arabic-Indic (0660–0669) and Extended Arabic-Indic (06F0–06F9) digits.
        $digits = [];
        for ($i = 0; $i <= 9; $i++) {
            $digits[mb_chr(0x0660 + $i, 'UTF-8')] = (string) $i;
            $digits[mb_chr(0x06F0 + $i, 'UTF-8')] = (string) $i;
        }
        $text = strtr($text, $digits);

        // Everything else (punctuation, symbols, other scripts) becomes a boundary,
        // so "250g" and "250 g" and "250-g" all tokenise the same way.
        $text = (string) preg_replace('/[^\p{Arabic}a-z0-9]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * The normalised haystack for one product: its own text plus any synonyms its
     * words unlock, de-duplicated.
     *
     * @param  array<int, string|null>  $parts
     */
    public static function terms(array $parts): string
    {
        $words = [];
        foreach ($parts as $part) {
            if ($part === null || $part === '') {
                continue;
            }
            foreach (explode(' ', self::normalize($part)) as $word) {
                if ($word !== '') {
                    $words[$word] = true;
                }
            }
        }

        foreach (self::synonymGroups() as $group) {
            if (array_intersect_key(array_flip($group), $words)) {
                foreach ($group as $word) {
                    $words[$word] = true;
                }
            }
        }

        return implode(' ', array_keys($words));
    }

    /**
     * Does every token of the query appear in the haystack?
     *
     * AND, not OR: "بوكس لوز" must mean both words, or a two-word query returns
     * most of the catalogue. This is the SERVER-side match, used to resolve the
     * `?q=` results page — deliberately substring-only, with no typo tolerance, so
     * it stays a plain predicate. The client's ranked matcher is a superset of it.
     */
    public static function matches(string $terms, string $query): bool
    {
        $tokens = array_filter(explode(' ', self::normalize($query)));
        if ($tokens === []) {
            return true;
        }

        foreach ($tokens as $token) {
            if (! str_contains($terms, $token)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, array<int, string>> */
    private static function synonymGroups(): array
    {
        static $groups = null;

        return $groups ??= array_map(
            fn (array $group) => array_values(array_unique(array_map(self::normalize(...), $group))),
            self::SYNONYMS,
        );
    }
}
