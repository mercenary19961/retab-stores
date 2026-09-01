<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductDescriptionWriter;
use App\Support\ProductNameTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The product-form helpers: an English name suggested from the Arabic one, and a
 * bilingual description built from the product's own attributes.
 *
 * Both are rule-based and offline — no API key, no network, deterministic. They
 * are SUGGESTIONS the admin edits before saving, which is what makes a
 * rule-based approach acceptable: being wrong costs a keystroke.
 */
class ProductSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    private function translator(): ProductNameTranslator
    {
        return new ProductNameTranslator;
    }

    // ------------------------------------------------------- the translator

    /** Real pairs from the live catalogue, one per structural rule. */
    public static function namePairs(): array
    {
        return [
            'variety + origin + grade + weight + unit' => [
                'خلاص أشيقر درجة أولى 250 جرام - الحبة', 'Khalas Ushaiger Grade 1 250g - Single',
            ],
            // 🔑 Grade 1 LEADS when there is no origin, TRAILS when there is.
            'grade leads without an origin' => ['سكري درجة اولى 500جم - حبه', 'Grade 1 Sukkari 500g - Single'],
            'box moves to the end' => ['بوكس سكري فاخر', 'Premium Sukkari Box'],
            'quality outranks process' => ['سكري القصيم مكنوز فاخر - حبة', 'Premium Pressed Sukkari Qassim - Single'],
            'process modifier leads' => ['خلاص القصيم مجروش درجة اولى 1 كيلو - حبة', 'Ground Khalas Qassim Grade 1 1kg - Single'],
            'brand leads where an origin would trail' => ['دبس رطاب', 'Retab Date Molasses'],
            'adjectival qualifier leads' => ['شابورة بالحبوب', 'Multigrain Rusk'],
            'prepositional qualifier trails' => ['خلاص درجة أولى بالشمر', 'Grade 1 Khalas with Fennel'],
            'quality suffix trails' => ['عجوة المدينة فله فاخر', 'Ajwa Madinah Premium Select'],
            'bare كيلو means 1kg' => ['خلاص اشيقر درجة اولى كيلو', 'Khalas Ushaiger Grade 1 1kg'],
            'نص كيلو means 500g' => ['شيشي درجة اولى نص كيلو', 'Grade 1 Shishi 500g'],
            'offer moves to the end' => ['عرض خلاص درجة أولى', 'Grade 1 Khalas Offer'],
            'arabic-indic digits' => ['سكري فاخر ١ كيلو - حبة', 'Premium Sukkari 1kg - Single'],
            // Spelling variants must fold onto one dictionary entry.
            'hamza-less alif folds' => ['خلاص اشيقر درجة اولى 250 جرام', 'Khalas Ushaiger Grade 1 250g'],
        ];
    }

    #[DataProvider('namePairs')]
    public function test_it_suggests_the_expected_english_name(string $ar, string $expected): void
    {
        $this->assertSame($expected, $this->translator()->translate($ar));
    }

    /**
     * 🔴 The safety property, and the reason this can be rule-based at all: an
     * unknown word yields NOTHING rather than a plausible-but-wrong name. A blank
     * field is obviously unfinished; a confident mistranslation an admin accepts
     * without re-reading is not.
     */
    public function test_it_refuses_rather_than_guessing_on_unknown_words(): void
    {
        $this->assertNull($this->translator()->translate('منتج غامض جدا'));
        $this->assertNull($this->translator()->translate('زعفران كشميري ممتاز'));
        $this->assertNull($this->translator()->translate(''));
        $this->assertNull($this->translator()->translate(null));
    }

    // ------------------------------------------------------ the description

    public function test_the_description_is_built_only_from_known_attributes(): void
    {
        $category = Category::create(['name_ar' => 'تمور', 'name_en' => 'Dates', 'slug' => 'dates']);
        $product = new Product(['name_ar' => 'سكري فاخر ١ كيلو - كرتون', 'name_en' => 'Premium Sukkari 1kg - Carton']);
        $product->setRelation('category', $category);

        $out = (new ProductDescriptionWriter)->write($product);

        // Every clause traces to a fact already on the record.
        $this->assertStringContainsString('سكري فاخر ١ كيلو - كرتون', $out['ar']);
        $this->assertStringContainsString('تمور', $out['ar'], 'the category');
        $this->assertStringContainsString('كيلوجرام', $out['ar'], 'the weight read off the name');
        $this->assertStringContainsString('الكرتون', $out['ar'], 'the packaging');

        $this->assertStringContainsString('Premium Sukkari 1kg - Carton', $out['en']);
        $this->assertStringContainsString('Dates', $out['en']);
        $this->assertStringContainsString('1kg', $out['en']);
        $this->assertStringContainsString('carton', $out['en']);
    }

    /**
     * 🔴 It must never invent a health, origin or quality claim — these are food
     * products sold in KSA and an unverifiable claim is a regulatory liability,
     * which is the whole reason this is templates and not a web search.
     */
    public function test_the_description_makes_no_unverifiable_claims(): void
    {
        $product = new Product(['name_ar' => 'خلاص أشيقر درجة أولى 250 جرام', 'name_en' => 'Khalas Ushaiger Grade 1 250g']);
        $product->setRelation('category', null);

        $out = (new ProductDescriptionWriter)->write($product);

        foreach (['organic', 'healthy', 'best', 'cures', 'natural remedy', 'finest in'] as $claim) {
            $this->assertStringNotContainsStringIgnoringCase($claim, $out['en'], "must not claim '{$claim}'");
        }
        $this->assertNotSame('', trim($out['ar']));
        $this->assertNotSame('', trim($out['en']));
    }

    public function test_a_product_with_no_category_still_gets_a_description(): void
    {
        $product = new Product(['name_ar' => 'قهوة نجدية', 'name_en' => 'Najdi Coffee']);
        $product->setRelation('category', null);

        $out = (new ProductDescriptionWriter)->write($product);

        $this->assertStringContainsString('قهوة نجدية', $out['ar']);
        $this->assertStringContainsString('Najdi Coffee', $out['en']);
    }

    // --------------------------------------------------------- the endpoint

    public function test_the_endpoint_returns_both_suggestions(): void
    {
        $category = Category::create(['name_ar' => 'تمور', 'name_en' => 'Dates', 'slug' => 'dates']);
        $staff = User::factory()->create(['role' => 'admin']);

        $body = $this->actingAs($staff)
            ->postJson('/admin/products/suggest', [
                'name_ar' => 'بوكس سكري فاخر',
                'name_en' => '',
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->json();

        $this->assertSame('Premium Sukkari Box', $body['name_en']);
        $this->assertNotEmpty($body['description']['ar']);
        $this->assertNotEmpty($body['description']['en']);
    }

    /** An unknown name yields a null name but still a usable description. */
    public function test_the_endpoint_returns_null_for_an_unknown_name(): void
    {
        $body = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson('/admin/products/suggest', ['name_ar' => 'شيء غير معروف تماما'])
            ->assertOk()
            ->json();

        $this->assertNull($body['name_en']);
        $this->assertNotEmpty($body['description']['ar']);
    }

    public function test_customers_cannot_reach_the_endpoint(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->postJson('/admin/products/suggest', ['name_ar' => 'خلاص'])
            ->assertForbidden();
    }
}
