<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\SearchText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Catalogue search.
 *
 * 🔑 The normalisation table below is DUPLICATED, identically, in
 * `resources/js/lib/search.test.ts`. `normalize` genuinely exists twice — PHP
 * builds each product's `terms` and matches the `?q=` results page, JS normalises
 * the query in the typeahead — and if the two drift, the typeahead silently stops
 * matching what the results page finds, with nothing failing anywhere. Keeping one
 * table on each side is what turns that into a test failure. Change a case here,
 * change it there.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{0: string, 1: string}> */
    public static function normalizeCases(): array
    {
        return [
            // Alef with hamza/madda folds to bare alef: the commonest spelling variance.
            ['أشيقر', 'اشيقر'],
            ['إسم', 'اسم'],
            ['آية', 'ايه'],
            // Ta marbuta → ha, so «علبة» and «علبه» are one word.
            ['علبة', 'علبه'],
            // Alef maqsura → ya, so «سكرى» finds «سكري».
            ['سكرى', 'سكري'],
            // Harakat and kashida are decoration. The kashida matters here
            // specifically: the storefront's own designed copy uses it.
            ['سُكَّري', 'سكري'],
            ['تمــــور', 'تمور'],
            // Hamza carriers.
            ['مؤمن', 'مومن'],
            ['رئيس', 'رييس'],
            ['ماء', 'ما'],
            // Arabic-Indic digits, both ranges.
            ['٢٥٠ جرام', '250 جرام'],
            ['۲۵۰', '250'],
            // Latin is lowercased; punctuation and symbols become word boundaries.
            ['Khalas  Ushaiger', 'khalas ushaiger'],
            ['250g - Carton', '250g carton'],
            ['  spaced   out  ', 'spaced out'],
            ['RTB-0004', 'rtb 0004'],
            // Mixed scripts survive together.
            ['خلاص Khalas 250g', 'خلاص khalas 250g'],
            ['', ''],
        ];
    }

    #[DataProvider('normalizeCases')]
    public function test_normalize_folds_text_to_its_searchable_form(string $input, string $expected): void
    {
        $this->assertSame($expected, SearchText::normalize($input));
    }

    public function test_normalize_is_idempotent(): void
    {
        foreach (self::normalizeCases() as [$input]) {
            $once = SearchText::normalize($input);
            $this->assertSame($once, SearchText::normalize($once), "not idempotent for: {$input}");
        }
    }

    // ------------------------------------------------------------------- terms

    public function test_terms_expands_synonyms_so_a_shopper_can_use_either_word(): void
    {
        // The catalogue writes "box" as «بوكس»; a shopper is as likely to type «علبة».
        $terms = SearchText::terms(['بوكس تمر محشي', 'Assorted Stuffed Dates Box']);

        foreach (['بوكس', 'علبه', 'صندوق', 'box', 'تمر', 'تمور', 'dates', 'محشي', 'stuffed'] as $word) {
            $this->assertStringContainsString($word, $terms, "expected «{$word}» in the haystack");
        }
    }

    public function test_terms_skips_nulls_and_de_duplicates(): void
    {
        $terms = SearchText::terms(['سكري', null, 'سكري', '']);
        $this->assertSame(1, substr_count(' '.$terms.' ', ' سكري '));
    }

    // ----------------------------------------------------------------- matches

    public function test_matches_ands_every_token(): void
    {
        $terms = SearchText::terms(['بوكس سكري محشي لوز']);

        $this->assertTrue(SearchText::matches($terms, 'بوكس لوز'));
        // 🔑 AND, not OR: with OR, a two-word query returns most of the catalogue.
        $this->assertFalse(SearchText::matches($terms, 'بوكس قهوة'));
    }

    public function test_matches_folds_the_query_the_same_way_as_the_index(): void
    {
        $terms = SearchText::terms(['خلاص أشيقر درجة أولى']);

        $this->assertTrue(SearchText::matches($terms, 'اشيقر'));
        $this->assertTrue(SearchText::matches($terms, 'أشيقر'));
        $this->assertTrue(SearchText::matches($terms, 'درجه'));
    }

    public function test_an_empty_query_matches_everything(): void
    {
        $this->assertTrue(SearchText::matches(SearchText::terms(['anything']), '   '));
    }

    // ------------------------------------------------------- the results page

    private function seedCatalogue(): void
    {
        Cache::forget(Product::SEARCH_INDEX_CACHE);
        $category = Category::create(['name_ar' => 'تمور فاخرة', 'name_en' => 'Premium Dates', 'slug' => 'premium', 'is_active' => true]);

        foreach ([
            ['خلاص أشيقر درجة أولى 250 جرام', 'Khalas Ushaiger Grade 1 250g'],
            ['بوكس سكري محشي لوز', 'Sukkari Box Stuffed with Almonds'],
            ['قهوة نجدية', 'Najdi Coffee'],
        ] as $i => [$ar, $en]) {
            Product::create([
                'category_id' => $category->id,
                'name_ar' => $ar,
                'name_en' => $en,
                'slug' => 'p-'.$i.'-'.uniqid(),
                'price' => 50,
                'sku' => 'RTB-000'.$i,
                'stock' => 5,
                'is_active' => true,
            ]);
        }
    }

    /** @return array<int, string> */
    private function search(string $q): array
    {
        return collect($this->get('/shop?q='.urlencode($q))->viewData('page')['props']['products']['data'])
            ->pluck('name_ar')
            ->all();
    }

    public function test_the_results_page_folds_arabic_spelling_variants(): void
    {
        $this->seedCatalogue();

        // 🔴 The bug this replaced: a raw SQL LIKE cannot fold أ→ا or ة→ه, so the
        // typeahead offered products and pressing Enter showed none.
        $this->assertSame(['خلاص أشيقر درجة أولى 250 جرام'], $this->search('اشيقر'));
        $this->assertSame(['خلاص أشيقر درجة أولى 250 جرام'], $this->search('درجه'));
        $this->assertSame(['بوكس سكري محشي لوز'], $this->search('سكرى'));
    }

    public function test_the_results_page_finds_an_english_query_and_a_synonym(): void
    {
        $this->seedCatalogue();

        $this->assertSame(['قهوة نجدية'], $this->search('coffee'));
        // «علبة» appears in no product name — only the synonym group puts it there.
        $this->assertSame(['بوكس سكري محشي لوز'], $this->search('علبة'));
    }

    public function test_the_results_page_matches_the_sku_and_the_category(): void
    {
        $this->seedCatalogue();

        $this->assertSame(['قهوة نجدية'], $this->search('RTB-0002'));
        $this->assertCount(3, $this->search('premium'));
    }

    public function test_the_results_page_ands_tokens(): void
    {
        $this->seedCatalogue();

        $this->assertSame(['بوكس سكري محشي لوز'], $this->search('بوكس لوز'));
        $this->assertSame([], $this->search('بوكس قهوة'));
    }

    public function test_an_unmatched_query_returns_nothing_rather_than_everything(): void
    {
        $this->seedCatalogue();

        // ⚠️ The `whereIn` gets an empty id list here. Worth pinning: an empty
        // filter that is skipped rather than applied would return the whole
        // catalogue, which reads as "search is broken" rather than "no results".
        $this->assertSame([], $this->search('zzzzzzz'));
    }

    // ------------------------------------------------------------- the index

    public function test_the_index_ships_the_prebuilt_haystack(): void
    {
        $this->seedCatalogue();

        $product = collect($this->getJson('/shop/search-index')->json('products'))
            ->firstWhere('name_ar', 'قهوة نجدية');

        $this->assertNotNull($product);
        // The client normalises only the QUERY, so `terms` must arrive normalised.
        $this->assertSame(SearchText::normalize($product['terms']), $product['terms']);
        $this->assertStringContainsString('coffee', $product['terms']);
        $this->assertStringContainsString('قهوه', $product['terms']);
        // Category and SKU are searchable too.
        $this->assertStringContainsString('premium', $product['terms']);
        $this->assertStringContainsString('rtb', $product['terms']);
    }
}
