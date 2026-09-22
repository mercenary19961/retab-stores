<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\User;
use App\Services\ChangeLog\ChangeLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product options in the change log.
 *
 * 🔴 THE GAP THIS CLOSES WAS THE MOST EXPENSIVE ONE IN THE AUDIT: an option
 * carries a PRICE. `logUpdated($product, …)` diffs the products table, and
 * options live in their own table — so raising «كرتون» from 69 to 96, or
 * deleting a size outright, was completely invisible to the change log while the
 * ordinary text edit in the same save was recorded in full.
 */
class ProductOptionChangeLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function product(): Product
    {
        $category = Category::create(['name_ar' => 'تمور', 'slug' => 'dates-'.uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name_ar' => 'خلاص', 'slug' => 'k-'.uniqid(),
            'price' => 5, 'sku' => 'K-'.uniqid(), 'stock' => 100, 'is_active' => false,
        ]);
        $product->images()->create(['path' => 'products/seed.jpg', 'sort_order' => 1, 'is_primary' => true]);

        return $product;
    }

    /** The PUT payload the product form sends, with its options. */
    private function payload(Product $p, array $options): array
    {
        return [
            'category_id' => $p->category_id,
            'name_ar' => $p->name_ar,
            'slug' => $p->slug,
            'price' => (float) $p->price,
            'sku' => $p->sku,
            'stock' => $p->stock,
            'options' => $options,
        ];
    }

    public function test_adding_an_option_is_recorded(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin())
            ->put("/admin/products/{$product->id}", $this->payload($product, [
                ['id' => null, 'label_ar' => 'كرتون', 'amount' => null, 'is_box' => true, 'price' => 69, 'stock_units' => 12],
            ]))
            ->assertRedirect();

        $option = $product->options()->firstOrFail();
        $log = ActivityLog::where('subject_type', ProductOption::class)
            ->where('subject_id', $option->id)
            ->where('action', ActivityLog::ACTION_CREATED)
            ->firstOrFail();

        $this->assertStringContainsString('كرتون', (string) $log->label);
    }

    /**
     * 🔴 The one that matters: a price change on a size or carton, which used to
     * leave no trace at all.
     */
    public function test_changing_an_options_price_is_recorded_and_can_be_undone(): void
    {
        $product = $this->product();
        $option = $product->options()->create([
            'label_ar' => 'كرتون', 'amount' => null, 'is_box' => true,
            'price' => 69, 'stock_units' => 12, 'sort_order' => 1,
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/products/{$product->id}", $this->payload($product, [
                ['id' => $option->id, 'label_ar' => 'كرتون', 'amount' => null, 'is_box' => true, 'price' => 96, 'stock_units' => 12],
            ]))
            ->assertRedirect();

        $this->assertSame('96.00', $option->fresh()->price);

        $log = ActivityLog::where('subject_type', ProductOption::class)
            ->where('subject_id', $option->id)
            ->where('action', ActivityLog::ACTION_UPDATED)
            ->firstOrFail();

        $this->assertSame('69.00', (string) ($log->old_data['price'] ?? null));
        $this->assertSame('96.00', (string) ($log->new_data['price'] ?? null));

        $this->assertTrue(app(ChangeLogService::class)->revert($log)->ok);
        $this->assertSame('69.00', $option->fresh()->price, 'the old price comes back');
    }

    /**
     * ⚠️ Removing a size soft-deletes it, so undoing brings it back with its
     * price rather than the client retyping it from memory.
     */
    public function test_removing_an_option_is_recorded_and_can_be_undone(): void
    {
        $product = $this->product();
        $option = $product->options()->create([
            'label_ar' => '500 جرام', 'amount' => 500, 'price' => 10, 'stock_units' => 2, 'sort_order' => 1,
        ]);

        // The form posts the remaining options; the missing one is the removal.
        $this->actingAs($this->admin())
            ->put("/admin/products/{$product->id}", $this->payload($product, []))
            ->assertRedirect();

        $this->assertSame(0, $product->options()->count());
        $this->assertNotNull(ProductOption::withTrashed()->find($option->id));

        $log = ActivityLog::where('subject_type', ProductOption::class)
            ->where('subject_id', $option->id)
            ->where('action', ActivityLog::ACTION_DELETED)
            ->firstOrFail();

        $this->assertTrue(app(ChangeLogService::class)->revert($log)->ok);
        $this->assertSame(1, $product->options()->count());
        $this->assertSame('10.00', $product->options()->first()->price);
    }

    /**
     * ⚠️ A save that leaves the options alone must not write an entry — the log
     * would fill with noise on every unrelated text edit, which is exactly what
     * makes an audit trail stop being read.
     */
    public function test_a_save_that_does_not_touch_the_options_logs_nothing_for_them(): void
    {
        $product = $this->product();
        $option = $product->options()->create([
            'label_ar' => 'كرتون', 'amount' => null, 'is_box' => true,
            'price' => 69, 'stock_units' => 12, 'sort_order' => 1,
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/products/{$product->id}", array_merge(
                $this->payload($product, [
                    ['id' => $option->id, 'label_ar' => 'كرتون', 'amount' => null, 'is_box' => true, 'price' => 69, 'stock_units' => 12],
                ]),
                ['name_ar' => 'خلاص فاخر'],
            ))
            ->assertRedirect();

        $this->assertSame('خلاص فاخر', $product->fresh()->name_ar);
        $this->assertSame(0, ActivityLog::where('subject_type', ProductOption::class)->count());
    }
}
