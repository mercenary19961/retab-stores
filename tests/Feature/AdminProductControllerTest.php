<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function category(): Category
    {
        return Category::create(['name_ar' => 'تمور', 'slug' => 'dates-'.uniqid()]);
    }

    private function validPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->id,
            'name_ar' => 'تمر سكري',
            'price' => 75,
            'sku' => 'SUK-'.uniqid(),
            'stock' => 100,
            'is_active' => true,
            'is_featured' => false,
        ], $overrides);
    }

    public function test_update_syncs_options_create_update_and_delete(): void
    {
        $category = $this->category();
        $product = Product::create($this->validPayload($category, ['slug' => 'p-opt']));
        $product->images()->create(['path' => 'products/seed.jpg', 'sort_order' => 1, 'is_primary' => true]);
        $keep = $product->options()->create(['label_ar' => '250 جرام', 'amount' => 250, 'price' => 5, 'stock_units' => 1]);
        $product->options()->create(['label_ar' => 'يُحذف', 'amount' => 500, 'price' => 10, 'stock_units' => 2]);

        $this->actingAs($this->staff())
            ->put("/admin/products/{$product->id}", $this->validPayload($category, [
                'slug' => 'p-opt',
                'sku' => $product->sku,
                'options' => [
                    // existing row updated (id carried)
                    ['id' => $keep->id, 'label_ar' => '250 جرام', 'amount' => 250, 'price' => 6, 'price_overridden' => true, 'stock_units' => 1, 'is_active' => true],
                    // brand-new carton (no weight, manual price)
                    ['label_ar' => 'كرتون', 'amount' => null, 'price' => 69, 'price_overridden' => true, 'stock_units' => 20, 'is_active' => true],
                ],
            ]))
            ->assertRedirect('/admin/products');

        $product->refresh()->load('options');
        $this->assertCount(2, $product->options); // 1 kept+updated, 1 created, 1 deleted
        $this->assertEquals(6.0, (float) $product->options->firstWhere('id', $keep->id)->price);
        $this->assertNotNull($product->options->firstWhere('label_ar', 'كرتون'));
        $this->assertNull($product->options->firstWhere('label_ar', 'يُحذف'));
        // Listing price is now the cheapest surviving option (250g at 6), not 69.
        $this->assertSame(6.0, $product->fresh()->effectivePrice());
    }

    public function test_a_forged_option_id_cannot_hijack_another_products_option(): void
    {
        $category = $this->category();
        $a = Product::create($this->validPayload($category, ['slug' => 'a', 'sku' => 'A1']));
        $a->images()->create(['path' => 'products/a.jpg', 'sort_order' => 1, 'is_primary' => true]);
        $b = Product::create($this->validPayload($category, ['slug' => 'b', 'sku' => 'B1']));
        $bOption = $b->options()->create(['label_ar' => 'ب', 'amount' => 250, 'price' => 5, 'stock_units' => 1]);

        // Product A submits B's option id → it must be treated as a NEW option on
        // A (the id ignored), never as an edit of B's row.
        $this->actingAs($this->staff())
            ->put("/admin/products/{$a->id}", $this->validPayload($category, [
                'slug' => 'a', 'sku' => 'A1',
                'options' => [['id' => $bOption->id, 'label_ar' => 'مسروق', 'amount' => 250, 'price' => 99, 'stock_units' => 1, 'is_active' => true]],
            ]))
            ->assertRedirect('/admin/products');

        // B's option is untouched; A got its own new row.
        $this->assertEquals(5.0, (float) $bOption->fresh()->price);
        $this->assertSame(1, $a->fresh()->options()->count());
        $this->assertNotSame($bOption->id, $a->options()->first()->id);
    }

    public function test_customers_cannot_reach_products(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get('/admin/products')->assertForbidden();
    }

    public function test_staff_can_list_products(): void
    {
        $category = $this->category();
        Product::create($this->validPayload($category, ['slug' => 'p-1']));

        $this->actingAs($this->staff())
            ->get('/admin/products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/index')
                ->has('products.data', 1));
    }

    public function test_per_page_is_honored_and_whitelisted(): void
    {
        $category = $this->category();
        for ($i = 0; $i < 11; $i++) {
            Product::create($this->validPayload($category, ['slug' => "pp-{$i}", 'sku' => "PP-{$i}"]));
        }

        // A whitelisted size limits the page and echoes back in filters.
        $this->actingAs($this->staff())
            ->get('/admin/products?per_page=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 10)->where('filters.per_page', 10));

        // An arbitrary size is rejected and falls back to the default (20 → all 11).
        $this->actingAs($this->staff())
            ->get('/admin/products?per_page=999')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 11)->where('filters.per_page', 20));
    }

    public function test_store_creates_product_and_auto_generates_slug(): void
    {
        Storage::fake('public');
        $category = $this->category();

        $this->actingAs($this->staff())
            ->post('/admin/products', $this->validPayload($category, [
                'name_en' => 'Sukkari Dates',
                'sku' => 'SUK-1',
                'images' => [UploadedFile::fake()->image('a.jpg')],
            ]))
            ->assertRedirect(route('admin.products.index'));

        $product = Product::firstOrFail();
        $this->assertSame('sukkari-dates', $product->slug); // derived from name_en
        $this->assertSame(100, $product->stock);
    }

    public function test_store_requires_at_least_one_image(): void
    {
        $category = $this->category();

        $this->actingAs($this->staff())
            ->post('/admin/products', $this->validPayload($category, ['sku' => 'NO-IMG']))
            ->assertSessionHasErrors('images');

        $this->assertSame(0, Product::count());
    }

    public function test_update_is_rejected_when_the_product_has_no_images(): void
    {
        $category = $this->category();
        $product = Product::create($this->validPayload($category, ['slug' => 'p-noimg', 'sku' => 'NOIMG-1']));

        $this->actingAs($this->staff())
            ->put("/admin/products/{$product->id}", $this->validPayload($category, ['slug' => 'p-noimg', 'sku' => $product->sku, 'stock' => 3]))
            ->assertSessionHasErrors('images');

        $this->assertSame(100, $product->fresh()->stock); // save was blocked
    }

    public function test_store_rejects_sale_price_not_below_price(): void
    {
        $category = $this->category();

        $this->actingAs($this->staff())
            ->post('/admin/products', $this->validPayload($category, ['price' => 50, 'sale_price' => 60]))
            ->assertSessionHasErrors('sale_price');
    }

    public function test_update_changes_fields(): void
    {
        $category = $this->category();
        $product = Product::create($this->validPayload($category, ['slug' => 'p-edit']));
        $product->images()->create(['path' => 'products/seed.jpg', 'sort_order' => 1, 'is_primary' => true]);

        $this->actingAs($this->staff())
            ->put("/admin/products/{$product->id}", $this->validPayload($category, [
                'slug' => 'p-edit',
                'sku' => $product->sku,
                'name_ar' => 'تمر خلاص',
                'stock' => 5,
            ]))
            ->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertSame('تمر خلاص', $product->name_ar);
        $this->assertSame(5, $product->stock);
    }

    public function test_export_csv_contains_headers_and_rows(): void
    {
        $category = $this->category();
        Product::create($this->validPayload($category, ['slug' => 'exp-1', 'sku' => 'EXP-1', 'name_ar' => 'تمر سكري فاخر', 'smacc_sku' => 'SMK-1']));

        $response = $this->actingAs($this->staff())->get('/admin/products/export?format=csv');
        $response->assertOk();
        $this->assertStringContainsString('.csv', $response->headers->get('content-disposition'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('smacc_sku', $body);   // header row
        $this->assertStringContainsString('تمر سكري فاخر', $body); // data row
        $this->assertStringContainsString('SMK-1', $body);
    }

    public function test_export_respects_category_filter(): void
    {
        $dates = $this->category();
        $other = Category::create(['name_ar' => 'أخرى', 'slug' => 'other-'.uniqid()]);
        Product::create($this->validPayload($dates, ['slug' => 'in-1', 'sku' => 'IN-1', 'name_ar' => 'منتج داخل الفلتر']));
        Product::create($this->validPayload($other, ['slug' => 'out-1', 'sku' => 'OUT-1', 'name_ar' => 'منتج خارج الفلتر']));

        $body = $this->actingAs($this->staff())
            ->get("/admin/products/export?format=csv&category={$dates->id}")
            ->streamedContent();

        $this->assertStringContainsString('منتج داخل الفلتر', $body);
        $this->assertStringNotContainsString('منتج خارج الفلتر', $body);
    }

    public function test_export_json_returns_products(): void
    {
        $category = $this->category();
        Product::create($this->validPayload($category, ['slug' => 'j-1', 'sku' => 'J-1']));

        $body = $this->actingAs($this->staff())
            ->get('/admin/products/export?format=json')
            ->streamedContent();

        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('smacc_sku', $data[0]);
    }

    public function test_export_xlsx_returns_spreadsheet(): void
    {
        $category = $this->category();
        Product::create($this->validPayload($category, ['slug' => 'x-1', 'sku' => 'X-1']));

        $response = $this->actingAs($this->staff())->get('/admin/products/export?format=xlsx');
        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_customers_cannot_export(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get('/admin/products/export?format=csv')->assertForbidden();
    }

    public function test_destroy_soft_deletes(): void
    {
        $category = $this->category();
        $product = Product::create($this->validPayload($category, ['slug' => 'p-del']));

        $this->actingAs($this->staff())
            ->delete("/admin/products/{$product->id}")
            ->assertRedirect(route('admin.products.index'));

        $this->assertSoftDeleted($product);
    }

    public function test_toggle_active_hides_a_live_product(): void
    {
        $category = $this->category();
        $product = Product::create($this->validPayload($category, ['slug' => 'p-live', 'is_active' => true]));
        $product->images()->create(['path' => 'products/seed.jpg', 'sort_order' => 1, 'is_primary' => true]);

        $this->actingAs($this->staff())
            ->from('/admin/products')
            ->patch("/admin/products/{$product->id}/toggle-active")
            ->assertRedirect('/admin/products')
            ->assertSessionHas('success');

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_toggle_active_shows_a_hidden_product_that_has_an_image(): void
    {
        $category = $this->category();
        $product = Product::create($this->validPayload($category, ['slug' => 'p-hidden', 'is_active' => false]));
        $product->images()->create(['path' => 'products/seed.jpg', 'sort_order' => 1, 'is_primary' => true]);

        $this->actingAs($this->staff())
            ->from('/admin/products')
            ->patch("/admin/products/{$product->id}/toggle-active")
            ->assertRedirect('/admin/products')
            ->assertSessionHas('success');

        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_toggle_active_is_blocked_when_the_product_has_no_image(): void
    {
        $category = $this->category();
        $product = Product::create($this->validPayload($category, ['slug' => 'p-noimg-toggle', 'is_active' => false]));

        $this->actingAs($this->staff())
            ->from('/admin/products')
            ->patch("/admin/products/{$product->id}/toggle-active")
            ->assertRedirect('/admin/products')
            ->assertSessionHas('error');

        $this->assertFalse($product->fresh()->is_active); // still hidden
    }
}
