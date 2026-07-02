<?php

namespace Tests\Feature\Smacc;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Smacc\SmaccImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmaccImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SmaccImportService
    {
        return app(SmaccImportService::class);
    }

    private function product(string $smaccSku, int $stock, ?string $barcode = null): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name_ar' => 'منتج',
            'slug' => 'p-' . uniqid(),
            'price' => 50,
            'sku' => 'SKU-' . uniqid(),
            'smacc_sku' => $smaccSku,
            'barcode' => $barcode,
            'stock' => $stock,
        ]);
    }

    /** Write CSV content to a temp file and return the path. */
    private function csv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'smacc') . '.csv';
        file_put_contents($path, $content);

        return $path;
    }

    public function test_parse_maps_columns_and_strips_bom(): void
    {
        $path = $this->csv("\xEF\xBB\xBFsmacc_sku,quantity\nSM-1,42\n");

        $rows = $this->service()->parse($path);

        $this->assertCount(1, $rows);
        $this->assertSame('SM-1', $rows[0]['smacc_sku']);
        $this->assertSame(42, $rows[0]['stock']);
    }

    public function test_diff_buckets_matched_unchanged_unmatched_and_invalid(): void
    {
        $this->product('SM-1', 10);  // will change → 5
        $this->product('SM-2', 7);   // unchanged → 7

        $rows = $this->service()->parse($this->csv(
            "smacc_sku,stock\nSM-1,5\nSM-2,7\nSM-NOPE,3\nSM-1,abc\n"
        ));

        $diff = $this->service()->diff($rows);

        $this->assertCount(1, $diff['matched']);
        $this->assertSame(10, $diff['matched'][0]['old']);
        $this->assertSame(5, $diff['matched'][0]['new']);
        $this->assertCount(1, $diff['unchanged']);
        $this->assertCount(1, $diff['unmatched']);
        $this->assertCount(1, $diff['invalid']);
    }

    public function test_apply_updates_stock_logs_and_stamps_last_synced(): void
    {
        $product = $this->product('SM-1', 10);

        $rows = $this->service()->parse($this->csv("smacc_sku,stock\nSM-1,3\n"));
        $log = $this->service()->apply($rows, userId: null);

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame('stock_import', $log->action);
        $this->assertSame(1, $log->changes['summary']['updated']);
        $this->assertNotNull(Setting::get(SmaccImportService::LAST_SYNCED_KEY));
    }

    public function test_apply_is_idempotent(): void
    {
        $this->product('SM-1', 10);
        $path = $this->csv("smacc_sku,stock\nSM-1,3\n");

        $this->service()->apply($this->service()->parse($path));
        $second = $this->service()->apply($this->service()->parse($path));

        $this->assertSame(0, $second->changes['summary']['updated']); // nothing left to change
    }

    public function test_barcode_fallback_match(): void
    {
        $product = $this->product('SM-X', 10, barcode: '6281000000001');

        // Row has no smacc_sku column value match, only barcode.
        $rows = $this->service()->parse($this->csv("barcode,stock\n6281000000001,2\n"));
        $this->service()->apply($rows);

        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_undo_restores_previous_stock(): void
    {
        $product = $this->product('SM-1', 10);

        $log = $this->service()->apply($this->service()->parse($this->csv("smacc_sku,stock\nSM-1,3\n")));
        $this->assertSame(3, $product->fresh()->stock);

        $this->service()->undo($log);

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertNotNull($log->fresh()->reverted_at);
    }

    public function test_undo_twice_throws(): void
    {
        $this->product('SM-1', 10);
        $log = $this->service()->apply($this->service()->parse($this->csv("smacc_sku,stock\nSM-1,3\n")));

        $this->service()->undo($log);

        $this->expectException(\RuntimeException::class);
        $this->service()->undo($log->fresh());
    }
}
