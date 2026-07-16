<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Change-log v2 semantics — including the compound-edit scenarios that broke
 * Sky Amman's snapshot design (full-record clobber, out-of-order resurrection,
 * silent no-op reverts). See CLAUDE.md → Change Log.
 */
class ChangeLogTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function product(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'is_active' => true]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name_ar' => 'منتج',
            'slug' => 'p-' . uniqid(),
            'price' => 50,
            'sku' => 'SKU-' . uniqid(),
            'stock' => 10,
        ], $overrides));
    }

    /** Full valid PUT payload mirroring the product's current state. */
    private function payload(Product $p, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $p->category_id,
            'name_ar' => $p->name_ar,
            'slug' => $p->slug,
            'price' => (float) $p->price,
            'sku' => $p->sku,
            'stock' => $p->stock,
        ], $overrides);
    }

    private function latestLog(): ActivityLog
    {
        return ActivityLog::latest('id')->firstOrFail();
    }

    public function test_product_update_snapshots_only_the_changed_fields(): void
    {
        $p = $this->product();

        $this->actingAs($this->staff())
            ->put("/admin/products/{$p->id}", $this->payload($p, ['stock' => 4]))
            ->assertRedirect();

        $log = $this->latestLog();
        $this->assertSame(ActivityLog::ACTION_UPDATED, $log->action);
        $this->assertSame(Product::class, $log->subject_type);
        $this->assertEquals(['stock' => 10], $log->old_data);
        $this->assertEquals(['stock' => 4], $log->new_data);
    }

    public function test_a_no_op_update_logs_nothing(): void
    {
        $p = $this->product();

        $this->actingAs($this->staff())
            ->put("/admin/products/{$p->id}", $this->payload($p))
            ->assertRedirect();

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_reverting_an_old_update_keeps_newer_edits_to_other_fields(): void
    {
        $p = $this->product(['name_ar' => 'تمر خلاص']);
        $staff = $this->staff();

        $this->actingAs($staff)->put("/admin/products/{$p->id}", $this->payload($p, ['stock' => 4]));
        $stockLog = $this->latestLog();

        $this->actingAs($staff)->put("/admin/products/{$p->id}", $this->payload($p->fresh(), ['name_ar' => 'تمر سكري']));

        $this->actingAs($staff)
            ->post("/admin/change-log/{$stockLog->id}/revert")
            ->assertSessionHas('success');

        $p->refresh();
        $this->assertSame(10, $p->stock);           // reverted
        $this->assertSame('تمر سكري', $p->name_ar); // newer edit untouched (Sky Amman clobbered this)

        $mirror = $this->latestLog();
        $this->assertSame(ActivityLog::ACTION_UPDATED, $mirror->action);
        $this->assertSame($stockLog->id, $mirror->reverts_log_id);
        $this->assertEquals(['stock' => 4], $mirror->old_data);
        $this->assertEquals(['stock' => 10], $mirror->new_data);
        $this->assertNotNull($stockLog->fresh()->reverted_at);
    }

    public function test_revert_is_refused_when_the_same_field_changed_again(): void
    {
        $p = $this->product();
        $staff = $this->staff();

        $this->actingAs($staff)->put("/admin/products/{$p->id}", $this->payload($p, ['stock' => 4]));
        $firstLog = $this->latestLog();

        $this->actingAs($staff)->put("/admin/products/{$p->id}", $this->payload($p->fresh(), ['stock' => 7]));

        $this->actingAs($staff)
            ->post("/admin/change-log/{$firstLog->id}/revert")
            ->assertSessionHas('revertConflict');

        $this->assertSame(7, $p->fresh()->stock);            // nothing written
        $this->assertNull($firstLog->fresh()->reverted_at);  // not stamped as done
    }

    public function test_redo_by_reverting_the_mirror_entry(): void
    {
        $p = $this->product();
        $staff = $this->staff();

        $this->actingAs($staff)->put("/admin/products/{$p->id}", $this->payload($p, ['stock' => 4]));
        $log = $this->latestLog();

        $this->actingAs($staff)->post("/admin/change-log/{$log->id}/revert")->assertSessionHas('success');
        $this->assertSame(10, $p->fresh()->stock);
        $mirror = $this->latestLog();

        // Redo = revert the revert. Same machinery, fully logged.
        $this->actingAs($staff)->post("/admin/change-log/{$mirror->id}/revert")->assertSessionHas('success');
        $this->assertSame(4, $p->fresh()->stock);
        $this->assertSame($mirror->id, $this->latestLog()->reverts_log_id);
    }

    public function test_reverting_a_delete_restores_the_product(): void
    {
        $p = $this->product();
        $staff = $this->staff();

        $this->actingAs($staff)->delete("/admin/products/{$p->id}");
        $this->assertSoftDeleted('products', ['id' => $p->id]);
        $log = $this->latestLog();
        $this->assertSame(ActivityLog::ACTION_DELETED, $log->action);

        $this->actingAs($staff)->post("/admin/change-log/{$log->id}/revert")->assertSessionHas('success');

        $this->assertDatabaseHas('products', ['id' => $p->id, 'deleted_at' => null]);
        $this->assertSame(ActivityLog::ACTION_RESTORED, $this->latestLog()->action);
    }

    public function test_reverting_a_create_soft_deletes_the_product(): void
    {
        $staff = $this->staff();
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'is_active' => true]);

        $this->actingAs($staff)->post('/admin/products', [
            'category_id' => $category->id,
            'name_ar' => 'تمر جديد',
            'price' => 30,
            'sku' => 'SKU-NEW',
            'stock' => 5,
        ])->assertRedirect();

        $log = $this->latestLog();
        $this->assertSame(ActivityLog::ACTION_CREATED, $log->action);
        $productId = $log->subject_id;

        $this->actingAs($staff)->post("/admin/change-log/{$log->id}/revert")->assertSessionHas('success');

        $this->assertSoftDeleted('products', ['id' => $productId]);
        $mirror = $this->latestLog();
        $this->assertSame(ActivityLog::ACTION_DELETED, $mirror->action);
        $this->assertSame('تمر جديد', $mirror->old_data['name_ar']); // redo can restore the real state
    }

    public function test_an_already_reverted_entry_is_blocked(): void
    {
        $p = $this->product();
        $staff = $this->staff();

        $this->actingAs($staff)->put("/admin/products/{$p->id}", $this->payload($p, ['stock' => 4]));
        $log = $this->latestLog();

        $this->actingAs($staff)->post("/admin/change-log/{$log->id}/revert")->assertSessionHas('success');
        $this->actingAs($staff)->post("/admin/change-log/{$log->id}/revert")->assertSessionHas('error');

        $this->assertSame(10, $p->fresh()->stock); // second revert changed nothing
    }

    public function test_settings_revert_restores_old_values_and_conflicts_after_a_newer_change(): void
    {
        $staff = $this->staff();
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, '20');

        $this->actingAs($staff)->put('/admin/settings', [CheckoutService::SHIPPING_FEE_KEY => 25]);
        $firstLog = $this->latestLog();
        $this->assertSame(ActivityLog::SUBJECT_SETTINGS, $firstLog->subject_type);

        // Revert while untouched → back to 20, mirror entry written.
        $this->actingAs($staff)->post("/admin/change-log/{$firstLog->id}/revert")->assertSessionHas('success');
        $this->assertSame('20', (string) Setting::get(CheckoutService::SHIPPING_FEE_KEY));
        $this->assertSame($firstLog->id, $this->latestLog()->reverts_log_id);

        // Change it again, then try reverting the (already-reverted) first entry → blocked;
        // and reverting the mirror now conflicts because the value moved on.
        $mirror = $this->latestLog();
        $this->actingAs($staff)->put('/admin/settings', [CheckoutService::SHIPPING_FEE_KEY => 40]);

        $this->actingAs($staff)->post("/admin/change-log/{$mirror->id}/revert")->assertSessionHas('revertConflict');
        $this->assertSame('40', (string) Setting::get(CheckoutService::SHIPPING_FEE_KEY));
    }

    public function test_content_page_creation_is_audit_only(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post('/admin/content-pages', [
            'slug' => 'about',
            'title_ar' => 'من نحن',
            'body_ar' => 'نص',
            'is_published' => true,
        ])->assertRedirect();

        $log = $this->latestLog();
        $this->assertSame(ActivityLog::ACTION_CREATED, $log->action);
        $this->assertSame(ContentPage::class, $log->subject_type);

        $this->actingAs($staff)->post("/admin/change-log/{$log->id}/revert")->assertSessionHas('error');
        $this->assertDatabaseHas('content_pages', ['slug' => 'about']);
    }

    public function test_customers_cannot_access_the_change_log(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $log = ActivityLog::create(['action' => ActivityLog::ACTION_UPDATED]);

        $this->actingAs($customer)->get('/admin/change-log')->assertForbidden();
        $this->actingAs($customer)->post("/admin/change-log/{$log->id}/revert")->assertForbidden();
    }

    public function test_the_change_log_page_renders(): void
    {
        $p = $this->product();
        $staff = $this->staff();
        $this->actingAs($staff)->put("/admin/products/{$p->id}", $this->payload($p, ['stock' => 4]));

        $this->actingAs($staff)
            ->get('/admin/change-log')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/change-log/index')
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'updated')
                ->where('logs.data.0.revertable', true));
    }

    public function test_conflict_pins_the_blocking_change(): void
    {
        $staff = $this->staff();
        $product = $this->product(['price' => 50]);

        $this->actingAs($staff)->put("/admin/products/{$product->id}", $this->payload($product, ['price' => 60]));
        $firstEdit = $this->latestLog();

        $this->actingAs($staff)->put("/admin/products/{$product->id}", $this->payload($product->fresh(), ['price' => 75]));
        $laterEdit = $this->latestLog();

        // Reverting the older edit conflicts (price was changed again) and pins
        // the later edit as the blocker to undo first.
        $this->actingAs($staff)->post("/admin/change-log/{$firstEdit->id}/revert")
            ->assertSessionHas('revertConflict', fn ($c) => $c['blockerId'] === $laterEdit->id && $c['fields'] !== []);

        $this->assertSame('75.00', $product->fresh()->price); // nothing was clobbered
    }

    public function test_long_conflict_chain_redirects_to_direct_edit(): void
    {
        $staff = $this->staff();
        $product = $this->product(['price' => 10]);

        // The old edit we'll try to undo, then 6 more edits to price (chain > limit 5).
        $this->actingAs($staff)->put("/admin/products/{$product->id}", $this->payload($product, ['price' => 20]));
        $oldEdit = $this->latestLog();

        foreach (range(3, 8) as $i) {
            $this->actingAs($staff)->put("/admin/products/{$product->id}", $this->payload($product->fresh(), ['price' => $i * 10]));
        }

        // Too many later edits to guide through — no blocker link, offer direct edit.
        $this->actingAs($staff)->post("/admin/change-log/{$oldEdit->id}/revert")
            ->assertSessionHas('revertConflict', fn ($c) => $c['blockerId'] === null
                && $c['chainDepth'] >= 6
                && $c['editUrl'] === "/admin/products/{$product->id}/edit");
    }

    public function test_highlight_jumps_to_the_entry_page(): void
    {
        $staff = $this->staff();
        $product = $this->product();

        // 22 update entries → the oldest lands on page 2 (20 per page).
        $firstLogId = null;
        for ($i = 1; $i <= 22; $i++) {
            $this->actingAs($staff)->put("/admin/products/{$product->id}", $this->payload($product, ['name_ar' => "الاسم {$i}"]));
            if ($i === 1) {
                $firstLogId = $this->latestLog()->id;
            }
        }

        $this->actingAs($staff)->get("/admin/change-log?highlight={$firstLogId}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('highlight', $firstLogId)
                ->where('logs.current_page', 2)
                ->where('logs.data', fn ($data) => collect($data)->pluck('id')->contains($firstLogId)));
    }
}
