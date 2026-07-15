<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        return Category::create(['name_ar' => 'تمور', 'slug' => 'dates-' . uniqid()]);
    }

    private function validPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->id,
            'name_ar' => 'تمر سكري',
            'price' => 75,
            'sku' => 'SUK-' . uniqid(),
            'stock' => 100,
            'is_active' => true,
            'is_featured' => false,
        ], $overrides);
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

    public function test_store_creates_product_and_auto_generates_slug(): void
    {
        $category = $this->category();

        $this->actingAs($this->staff())
            ->post('/admin/products', $this->validPayload($category, ['name_en' => 'Sukkari Dates', 'sku' => 'SUK-1']))
            ->assertRedirect(route('admin.products.index'));

        $product = Product::firstOrFail();
        $this->assertSame('sukkari-dates', $product->slug); // derived from name_en
        $this->assertSame(100, $product->stock);
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
        $other = Category::create(['name_ar' => 'أخرى', 'slug' => 'other-' . uniqid()]);
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
}
