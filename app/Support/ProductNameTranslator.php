<?php

namespace App\Support;

/**
 * Suggests an English product name from the Arabic one.
 *
 * Rule-based and offline on purpose — no API key, no network, no per-press cost,
 * and the same input always gives the same output. It works because this
 * catalogue's names are formulaic: 91 products use only ~109 distinct Arabic
 * tokens, so the vocabulary is a closed set.
 *
 * 🔑 It is a SUGGESTION, never an answer. The admin sees it in the field and
 * edits before saving, which is what makes a rule-based approach acceptable
 * here: being wrong costs a keystroke, not a bad listing.
 *
 * 🔴 It returns NULL rather than guessing when a word is not in the dictionary.
 * A confidently-wrong English name that an admin accepts without reading is far
 * worse than no suggestion at all — a blank field is obviously unfinished, a
 * plausible-but-wrong one is not.
 *
 * ⚠️ The hard part is NOT vocabulary, it is word order. Arabic puts the head
 * noun first and modifiers after; English inverts several of them:
 *   بوكس سكري فاخر        → Premium Sukkari Box     (box moves to the end)
 *   سكري فاخر ١ كيلو      → Premium Sukkari 1kg     (فاخر moves to the front)
 *   خلاص القصيم مجروش     → Ground Khalas Qassim    (مجروش moves to the front)
 * A word-by-word mapping gets every one of those backwards.
 */
class ProductNameTranslator
{
    /** Head nouns: the product itself. Matched longest-first. */
    private const NOUNS = [
        'تمر محشي مشكل' => 'Assorted Stuffed Dates',
        'تمر محشي' => 'Stuffed Dates',
        'التمرة الذهبية' => 'Golden Date',
        'عجوة المدينة' => 'Ajwa Madinah',
        'صفاوي المدينة' => 'Safawi Madinah',
        'عنبرة المدينة' => 'Anbara Madinah',
        'دبس التمر' => 'Date Molasses',
        'مقلوبة التمر' => 'Date Maqluba',
        'معمول تمر' => 'Date Maamoul',
        'قهوة نجدية' => 'Najdi Coffee',
        'زبدة اللوز' => 'Almond Butter',
        'مكسرات مشكل' => 'Assorted Nuts',
        'تقديمات للتمر' => 'Date Serving Set',
        'إفطار صائم' => 'Iftar Pack',
        'افطار صائم' => 'Iftar Pack',
        'كيكة الزعفران' => 'Saffron Cake',
        'كيكة دخن' => 'Millet Cake',
        'معجون' => 'Paste',
        'شابورة' => 'Rusk',
        'بقسماط' => 'Breadcrumbs',
        'تمرية' => 'Tamriyah',
        'قدوع' => 'Qadou',
        'طحينة' => 'Tahini',
        'زعفران' => 'Saffron',
        'حنيني' => 'Hanini',
        'صينية' => 'Tray',
        'دبس' => 'Date Molasses',
        'اقط' => 'Aqit (Dried Yogurt)',
        'بسبوسة' => 'Basbousa',
        // Date varieties.
        'خلاصي' => 'Khalasi',
        'خلاص' => 'Khalas',
        'سكري' => 'Sukkari',
        'صقعي' => 'Sagai',
        'شيشي' => 'Shishi',
        'مجدول' => 'Medjool',
        'منيفي' => 'Munify',
        'دخيني' => 'Dukhaini',
        'عجوة' => 'Ajwa',
        'صفاوي' => 'Safawi',
        'عنبرة' => 'Anbara',
        'برحي' => 'Barhi',
        'روثانة' => 'Rothana',
        'مبروم' => 'Mabroom',
        'نبتة علي' => 'Nabtat Ali',
        'تمر' => 'Date',
    ];

    /**
     * Origin / sub-variety. TRAILS the head noun: خلاص أشيقر → "Khalas Ushaiger".
     */
    private const ORIGINS = [
        'أشيقر' => 'Ushaiger',
        'القصيم' => 'Qassim',
        'ساجر' => 'Sajir',
        'المدينة' => 'Madinah',
        'سنبلة' => 'Sunbula',
    ];

    /**
     * Brands. These LEAD in English where an origin trails — دبس رطاب is
     * "Retab Date Molasses", not "Date Molasses Retab". Keeping them in one map
     * with ORIGINS produced exactly that inversion.
     */
    private const BRANDS = [
        'رطاب' => 'Retab',
        'عصار' => 'Assar',
        'أصل الرشاقة' => 'Fitness',
        'أبو شال' => 'Abu Shal',
    ];

    /**
     * Modifiers that LEAD in English but trail in Arabic.
     *
     * ⚠️ Map order IS output order, and quality outranks process: the corpus
     * says "Premium Pressed Sukkari" and "Premium Hand-Ground Sukkari", never
     * "Pressed Premium".
     */
    private const LEADING = [
        'فاخر' => 'Premium',
        'ملكي' => 'Royal',
        'ميني' => 'Mini',
        'يدوي مجروش' => 'Hand-Ground',
        'مجروش' => 'Ground',
        'مكنوز' => 'Pressed',
        'مكبوس' => 'Pressed',
        'مفتل' => 'Rolled',
    ];

    /**
     * 🔑 Grade 1 moves depending on whether an origin is present:
     *   خلاص أشيقر درجة أولى 250 جرام → Khalas Ushaiger Grade 1 250g   (trails)
     *   سكري درجة اولى 500جم          → Grade 1 Sukkari 500g           (leads)
     * Read off the corpus, which has ~9 of each and no counter-example.
     */
    private const GRADE = ['درجة أولى' => 'Grade 1'];

    /** Trailing qualifiers, appended in order. */
    private const TRAILING = [
        'الكامل بر' => 'Whole Wheat',
        'بالحبوب و النخالة' => 'Grains & Bran',
        'بالكراميل المملح' => 'with Salted Caramel',
        'بالكريمة' => 'with Cream',
        'بالحبوب' => 'Multigrain',
        'باللوز' => 'with Almonds',
        'بالشمر' => 'with Fennel',
        'بالقمح' => 'Wheat',
        'بالبر' => 'Wheat (Burr)',
        'مع الصوص' => 'with Sauce',
        'vip' => 'VIP',
    ];

    /**
     * Appended last. فله فاخر trails where other quality words lead —
     * "Safawi Madinah Premium Select", three examples and no counter-example.
     */
    private const SUFFIXES = ['فله فاخر' => 'Premium Select'];

    /** Packaging suffix after a dash. */
    private const UNITS = [
        'حبة' => 'Single',
        'حبه' => 'Single',
        'كرتون' => 'Carton',
        'علبة' => 'Box',
        'ذهبي' => 'Gold',
        'فضي' => 'Silver',
        'مشكل كل الألوان' => 'Assorted Colors',
    ];

    public function translate(?string $arabic): ?string
    {
        $raw = trim((string) $arabic);
        if ($raw === '') {
            return null;
        }

        [$body, $suffix] = $this->splitPackagingSuffix($raw);
        $s = $this->normalise($body);

        $isBox = false;
        $isOffer = false;
        if (preg_match('/^بوكس\s+/u', $s)) {
            $isBox = true;                       // "بوكس X" → "X Box"
            $s = preg_replace('/^بوكس\s+/u', '', $s);
        }
        if (preg_match('/^عرض\s+/u', $s)) {
            $isOffer = true;                     // "عرض X" → "X Offer"
            $s = preg_replace('/^عرض\s+/u', '', $s);
        }
        if (preg_match('/^بوكس خشبي\s+/u', $s)) {
            $isBox = true;
            $s = preg_replace('/^بوكس خشبي\s+/u', '', $s);
        }

        $weight = null;
        $s = $this->extractWeight($s, $weight);

        // extractWeight leaves this marker so "Pack" can be placed after the
        // weight rather than wherever the token happened to sit.
        $pack = str_contains($s, 'PACK');
        $s = trim(str_replace('PACK', '', $s));

        $grade = $this->take($s, self::GRADE);
        $tailQuality = $this->take($s, self::SUFFIXES);   // before LEADING: 'فاخر' would claim half of it
        $lead = $this->take($s, self::LEADING);
        $trail = $this->take($s, self::TRAILING);
        $brand = $this->take($s, self::BRANDS);
        $noun = $this->take($s, self::NOUNS, once: true);
        $origin = $this->take($s, self::ORIGINS);

        // Anything left is vocabulary we do not know — refuse rather than guess.
        if (trim($s) !== '' || $noun === []) {
            return null;
        }

        // A qualifier phrased as "with …" is prepositional and trails the noun;
        // any other is adjectival and leads it. Derived from the value itself so
        // there is no second list to keep in step.
        $adjectival = array_values(array_filter($trail, fn ($t) => ! str_starts_with($t, 'with ')));
        $prepositional = array_values(array_filter($trail, fn ($t) => str_starts_with($t, 'with ')));
        $trail = $prepositional;

        // Grade 1 trails an origin but leads without one (see self::GRADE).
        $parts = $origin === []
            ? array_merge($grade, $lead, $adjectival, $brand, $noun)
            : array_merge($lead, $adjectival, $brand, $noun, $origin, $grade);

        // Qualifier before weight: "Grade 1 with Almonds 250g".
        $parts = array_merge($parts, $trail);
        $trail = [];

        if ($weight !== null) {
            $parts[] = $weight;
        }
        if ($pack) {
            $parts[] = 'Pack';   // "عبوة 1 كيلو" → "1kg Pack"
        }
        // "Box" sits with the noun, before trailing qualifiers:
        // بوكس خلاص مكبوس مع الصوص → "Pressed Khalas Box with Sauce".
        if ($isBox) {
            $parts[] = 'Box';
        }
        $parts = array_merge($parts, $trail);
        $parts = array_merge($parts, $tailQuality);
        if ($isOffer) {
            $parts[] = 'Offer';
        }

        $out = implode(' ', array_filter($parts));

        return $suffix !== null ? "{$out} - {$suffix}" : ($out ?: null);
    }

    /** "… - الكرتون" → ['…', 'Carton'] */
    private function splitPackagingSuffix(string $s): array
    {
        if (! preg_match('/^(.*?)\s*[-–]\s*([^-–]+)$/u', $s, $m)) {
            return [$s, null];
        }
        // Strip the definite article: the catalogue writes both "- حبة" and
        // "- الحبة" for the same thing, and both must hit one dictionary entry.
        $article = fn (string $x) => preg_replace('/^ال/u', '', $x);
        $tail = $article($this->normalise($m[2]));
        foreach (self::UNITS as $ar => $en) {
            if ($article($this->normalise($ar)) === $tail) {
                return [$m[1], $en];
            }
        }

        return [$s, null];
    }

    /** Fold spelling variants so one dictionary entry covers them all. */
    private function normalise(string $s): string
    {
        $s = preg_replace('/[\x{0640}\x{064B}-\x{0652}]/u', '', $s);   // tatweel + harakat
        // Same folding as App\Support\SearchText — the catalogue writes both
        // أشيقر and اشيقر for one place, and درجة أولى with and without hamza.
        $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي', 'ء' => '']);
        $s = strtr($s, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '،' => '.']);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /** Pull a weight out of the string and format it English-style. */
    private function extractWeight(string $s, ?string &$weight): string
    {
        $patterns = [
            '/\bنص كيلو\b/u' => '500g',
            '/(\d+(?:\.\d+)?)\s*كيلو\b/u' => null,     // numeric kg
            '/(\d+)\s*(?:جرام|جم|غم)\b/u' => null,     // numeric g
            '/\bكيلو\b/u' => '1kg',                    // bare "كيلو" means 1kg here
        ];
        foreach ($patterns as $re => $fixed) {
            if (preg_match($re, $s, $m)) {
                if ($fixed !== null) {
                    $weight = $fixed;
                } else {
                    $n = (float) $m[1];
                    $weight = str_contains($re, 'كيلو')
                        ? rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.').'kg'
                        : ((int) $n).'g';
                }
                $s = trim(preg_replace($re, ' ', $s, 1));
                $s = trim(preg_replace('/\bعبوه\b/u', 'PACK', $s));   // "عبوة 1 كيلو" → "… 1kg Pack"

                return preg_replace('/\s+/u', ' ', $s);
            }
        }

        return $s;
    }

    /**
     * Remove every entry of $map found in $s and return the English pieces, in
     * the map's own order so output is stable. Longest keys first, so "يدوي
     * مجروش" wins over "مجروش".
     *
     * @param  array<string,string>  $map
     * @return list<string>
     */
    private function take(string &$s, array $map, bool $once = false): array
    {
        $keys = array_keys($map);
        usort($keys, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $found = [];
        foreach ($keys as $ar) {
            $needle = $this->normalise($ar);
            if ($needle === '' || ! str_contains($s, $needle)) {
                continue;
            }
            $found[array_search($ar, array_keys($map), true)] = $map[$ar];
            $s = trim(preg_replace('/\s+/u', ' ', str_replace($needle, ' ', $s)));
            if ($once) {
                break;
            }
        }
        ksort($found);

        return array_values($found);
    }
}
