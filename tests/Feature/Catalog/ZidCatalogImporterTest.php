<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ZidCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZidCatalogImporterTest extends TestCase
{
    use RefreshDatabase;

    /** Write a fixture CSV mirroring the real column layout; returns its path. */
    private function fixtureCsv(): string
    {
        $header = 'row_ref,source,smacc_sku,zid_sku,name_ar,name_en,category,price,sale_price,quantity,weight,weight_unit,short_description_ar,keywords,image_count,images,old_zid_slug,published,flags';
        $rows = [
            '1,simple,RETAB001,RETAB001,تمر خلاص,,التمور,50,40,5,1,kg,وصف قصير,,0,,khalas-test,yes,',
            '2,simple,,ZID999,تمر مسودة,,,0,,,,,,,0,,draft-test,no,draft',
            '3,child,,ZID003,تمر محشي,Stuffed,التمور المحشية,69,,12,0.5,kg,وصف محشي,,0,,stuffed-test,yes,',
            '4,simple,RETAB001,ZID004,منتج متنوع,,منتجات متنوعة,40,,,,,,,0,,assorted-test,yes,',
        ];

        $path = tempnam(sys_get_temp_dir(), 'zidcsv') . '.csv';
        file_put_contents($path, $header . "\n" . implode("\n", $rows) . "\n");

        return $path;
    }

    public function test_it_imports_the_category_tree_and_products(): void
    {
        $path = $this->fixtureCsv();
        $result = app(ZidCatalogImporter::class)->import($path, withImages: false);
        unlink($path);

        $this->assertSame(4, $result->productsSaved);
        $this->assertSame(1, $result->drafts);

        // 2 nav parents + 6 leaf categories.
        $this->assertSame(2, Category::whereNull('parent_id')->count());
        $this->assertSame(6, Category::whereNotNull('parent_id')->count());
        $this->assertNotNull(Category::where('slug', 'dates')->first()->parent_id);
    }

    public function test_draft_rows_import_hidden_and_blank_stock_defaults(): void
    {
        $path = $this->fixtureCsv();
        app(ZidCatalogImporter::class)->import($path, withImages: false);
        unlink($path);

        $draft = Product::where('slug', 'draft-test')->first();
        $this->assertFalse($draft->is_active);
        // Blank Zid quantity → the sellable buffer.
        $this->assertSame(ZidCatalogImporter::BLANK_STOCK_DEFAULT, $draft->stock);
        // Uncategorised row defaults to التمور (dates).
        $this->assertSame('dates', $draft->category->slug);
    }

    public function test_prices_categories_and_smacc_keys_map_correctly(): void
    {
        $path = $this->fixtureCsv();
        app(ZidCatalogImporter::class)->import($path, withImages: false);
        unlink($path);

        $khalas = Product::where('slug', 'khalas-test')->first();
        $this->assertTrue($khalas->is_active);
        $this->assertSame(5, $khalas->stock);
        $this->assertSame('40.00', $khalas->sale_price);
        $this->assertSame('RETAB001', $khalas->smacc_sku);
        $this->assertSame('dates', $khalas->category->slug);

        $stuffed = Product::where('slug', 'stuffed-test')->first();
        $this->assertSame('stuffed-dates', $stuffed->category->slug);
        $this->assertSame(12, $stuffed->stock);
        $this->assertNull($stuffed->smacc_sku); // was blank

        // A duplicate SMACC key is dropped to null (nullable-unique column).
        $assorted = Product::where('slug', 'assorted-test')->first();
        $this->assertSame('assorted', $assorted->category->slug);
        $this->assertNull($assorted->smacc_sku);
    }

    public function test_it_is_idempotent_on_re_run(): void
    {
        $path = $this->fixtureCsv();
        $importer = app(ZidCatalogImporter::class);
        $importer->import($path, withImages: false);
        $importer->import($path, withImages: false);
        unlink($path);

        // Matched by slug → updated, not duplicated.
        $this->assertSame(4, Product::count());
    }
}
