<?php

namespace App\Support;

use App\Models\Product;

/**
 * Suggests a bilingual product description from what the store already knows.
 *
 * 🔑 Composed ONLY from facts already on the record — the product's own name,
 * its category, its weight and its packaging. It invents nothing.
 *
 * 🔴 That constraint is the entire design, and it is why this is templates
 * rather than a web search. These are FOOD products sold in Saudi Arabia:
 *   - copy lifted from another site is plagiarism, and duplicate content would
 *     undercut the SEO work this project has already paid for;
 *   - origin and health claims about dates carry SFDA regulatory weight, and an
 *     unverified claim is a liability rather than a time-saver;
 *   - nothing published elsewhere knows THIS store's weights and packaging.
 * Everything below is a restatement of stock facts plus a neutral serving line,
 * so there is nothing for the store to have to stand behind.
 *
 * Like the name translator, this is a starting point the admin edits — it exists
 * to remove the blank page, not to write the final copy.
 */
class ProductDescriptionWriter
{
    /** Weight → a natural phrase, in both languages. */
    private const SERVING_AR = 'تصلح للضيافة وتقديمها هدية في المناسبات.';

    private const SERVING_EN = 'Ideal for serving guests and for gifting.';

    /** @return array{ar:string, en:string} */
    public function write(Product $product): array
    {
        $nameAr = trim((string) $product->name_ar);
        $nameEn = trim((string) $product->name_en);
        $categoryAr = trim((string) $product->category?->name_ar);
        $categoryEn = trim((string) $product->category?->name_en);
        $weight = $this->weightFrom($nameAr);
        $packAr = $this->packagingAr($nameAr);
        $packEn = $this->packagingEn($nameAr);

        // ---- Arabic -------------------------------------------------------
        $ar = [];
        $ar[] = $nameAr !== ''
            ? "{$nameAr} من متجر رطاب للتمور."
            : 'منتج من متجر رطاب للتمور.';
        if ($categoryAr !== '') {
            $ar[] = "ضمن تشكيلة {$categoryAr}.";
        }
        if ($weight !== null) {
            $ar[] = "وزن العبوة {$weight['ar']}.";
        }
        if ($packAr !== null) {
            $ar[] = $packAr;
        }
        $ar[] = self::SERVING_AR;

        // ---- English ------------------------------------------------------
        $en = [];
        $en[] = $nameEn !== ''
            ? "{$nameEn} from Retab Dates."
            : ($nameAr !== '' ? "{$nameAr} from Retab Dates." : 'A product from Retab Dates.');
        if ($categoryEn !== '') {
            $en[] = "Part of our {$categoryEn} range.";
        }
        if ($weight !== null) {
            $en[] = "Net weight {$weight['en']}.";
        }
        if ($packEn !== null) {
            $en[] = $packEn;
        }
        $en[] = self::SERVING_EN;

        return [
            'ar' => implode(' ', $ar),
            'en' => implode(' ', $en),
        ];
    }

    /** @return array{ar:string, en:string}|null */
    private function weightFrom(string $name): ?array
    {
        $s = strtr($name, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '،' => '.']);

        if (preg_match('/\bنص كيلو\b/u', $s)) {
            return ['ar' => '٥٠٠ جرام', 'en' => '500g'];
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*كيلو\b/u', $s, $m)) {
            $kg = rtrim(rtrim(number_format((float) $m[1], 3, '.', ''), '0'), '.');

            return ['ar' => "{$kg} كيلوجرام", 'en' => "{$kg}kg"];
        }
        if (preg_match('/(\d+)\s*(?:جرام|جم|غم)\b/u', $s, $m)) {
            return ['ar' => "{$m[1]} جرام", 'en' => "{$m[1]}g"];
        }
        if (preg_match('/\bكيلو\b/u', $s)) {
            return ['ar' => 'كيلوجرام واحد', 'en' => '1kg'];
        }

        return null;
    }

    private function packagingAr(string $name): ?string
    {
        return match (true) {
            (bool) preg_match('/كرتون/u', $name) => 'يُباع بالكرتون.',
            (bool) preg_match('/بوكس|علبة/u', $name) => 'يأتي في علبة هدايا.',
            (bool) preg_match('/صينية/u', $name) => 'يأتي في صينية تقديم.',
            (bool) preg_match('/حبة|حبه/u', $name) => 'تُباع بالحبة.',
            default => null,
        };
    }

    private function packagingEn(string $name): ?string
    {
        return match (true) {
            (bool) preg_match('/كرتون/u', $name) => 'Sold by the carton.',
            (bool) preg_match('/بوكس|علبة/u', $name) => 'Presented in a gift box.',
            (bool) preg_match('/صينية/u', $name) => 'Presented on a serving tray.',
            (bool) preg_match('/حبة|حبه/u', $name) => 'Sold as a single unit.',
            default => null,
        };
    }
}
