<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Discount\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DiscountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('secret'), 'role' => 'admin']);
    }

    private function product(float $price, ?int $categoryId = null): Product
    {
        $categoryId ??= Category::create(['name_ar' => 'ت', 'slug' => 'c-' . uniqid()])->id;

        return Product::create([
            'category_id' => $categoryId, 'name_ar' => 'م', 'slug' => 'p-' . uniqid(),
            'price' => $price, 'sku' => 'SK-' . uniqid(), 'stock' => 10, 'is_active' => true,
        ]);
    }

    public function test_bulk_apply_discounts_a_category_only(): void
    {
        $cat = Category::create(['name_ar' => 'تمور', 'slug' => 'dates']);
        $a = $this->product(100, $cat->id);
        $b = $this->product(50, $cat->id);
        $other = $this->product(80); // different category — must be untouched

        app(DiscountService::class)->bulkApply(20, $cat->id, null, null, null);

        $this->assertEquals(80.00, (float) $a->fresh()->sale_price);
        $this->assertEquals(40.00, (float) $b->fresh()->sale_price);
        $this->assertNull($other->fresh()->sale_price);
    }

    public function test_sale_window_controls_whether_the_product_is_on_sale(): void
    {
        $svc = app(DiscountService::class);

        $scheduled = $this->product(100);
        $svc->bulkApply(20, null, Carbon::now()->addDay(), null, null);
        $this->assertEquals(80.00, (float) $scheduled->fresh()->sale_price);
        $this->assertFalse($scheduled->fresh()->isOnSale());          // starts tomorrow
        $this->assertSame('scheduled', $scheduled->fresh()->saleStatus());

        $expired = $this->product(100);
        $svc->bulkApply(20, null, null, Carbon::now()->subDay(), null);
        $this->assertFalse($expired->fresh()->isOnSale());            // ended yesterday
        $this->assertSame('expired', $expired->fresh()->saleStatus());

        $active = $this->product(100);
        $svc->bulkApply(20, null, null, null, null);
        $this->assertTrue($active->fresh()->isOnSale());              // no window = live
    }

    public function test_csv_import_applies_per_row_percentages(): void
    {
        $p1 = $this->product(100);
        $p2 = $this->product(40);
        $svc = app(DiscountService::class);

        $path = tempnam(sys_get_temp_dir(), 'disc');
        file_put_contents($path, "sku,discount_percent\n{$p1->sku},30\n{$p2->sku},25\nGHOST-SKU,10\n");

        $rows = $svc->parse($path);
        $diff = $svc->diff($rows);
        $this->assertCount(2, $diff['matched']);
        $this->assertCount(1, $diff['unmatched']);

        $svc->applyImport($rows, null, null, null);
        unlink($path);

        $this->assertEquals(70.00, (float) $p1->fresh()->sale_price);
        $this->assertEquals(30.00, (float) $p2->fresh()->sale_price);
    }

    public function test_clear_and_undo_restore_prices(): void
    {
        $p = $this->product(100);
        $svc = app(DiscountService::class);

        $log = $svc->bulkApply(20, null, null, null, null);
        $this->assertEquals(80.00, (float) $p->fresh()->sale_price);

        // Undo restores the pre-discount state (was no sale).
        $svc->undo($log, null);
        $this->assertNull($p->fresh()->sale_price);

        // Re-apply then clear.
        $svc->bulkApply(20, null, null, null, null);
        $svc->clear([$p->id], null);
        $this->assertNull($p->fresh()->sale_price);
    }

    public function test_bulk_apply_route_works_for_admin(): void
    {
        $p = $this->product(100);

        $this->actingAs($this->admin())->post('/admin/discounts/apply', [
            'percent' => 25,
            'ends_at' => '2099-01-01T00:00',
        ])->assertSessionHas('success');

        $this->assertEquals(75.00, (float) $p->fresh()->sale_price);
        $this->assertTrue($p->fresh()->isOnSale());
    }
}
