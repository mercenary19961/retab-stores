<?php

namespace Tests\Feature\Smacc;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function product(string $smaccSku, int $stock): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name_ar' => 'منتج',
            'slug' => 'p-' . uniqid(),
            'price' => 50,
            'sku' => 'SKU-' . uniqid(),
            'smacc_sku' => $smaccSku,
            'stock' => $stock,
        ]);
    }

    private function upload(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('stock.csv', $csv);
    }

    public function test_customers_are_forbidden(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get('/admin/stock-import')->assertForbidden();
    }

    public function test_preview_shows_the_diff(): void
    {
        Storage::fake('local');
        $this->product('SM-1', 10);

        $this->actingAs($this->staff())
            ->post('/admin/stock-import/preview', ['file' => $this->upload("smacc_sku,stock\nSM-1,4\n")])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/stock-import/preview')
                ->has('token')
                ->has('diff.matched', 1)
                ->where('diff.matched.0.new', 4));
    }

    public function test_full_preview_then_apply_updates_stock(): void
    {
        Storage::fake('local');
        $product = $this->product('SM-1', 10);
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post('/admin/stock-import/preview', ['file' => $this->upload("smacc_sku,stock\nSM-1,4\n")])
            ->assertOk();

        $token = basename(Storage::disk('local')->files('smacc-imports')[0]);

        $this->actingAs($staff)
            ->post('/admin/stock-import/apply', ['token' => $token])
            ->assertRedirect(route('admin.stock-import.index'));

        $this->assertSame(4, $product->fresh()->stock);
        $this->assertDatabaseHas('activity_logs', ['action' => 'stock_import']);
    }

    public function test_apply_with_expired_token_redirects_with_error(): void
    {
        Storage::fake('local');

        $this->actingAs($this->staff())
            ->post('/admin/stock-import/apply', ['token' => 'does-not-exist.csv'])
            ->assertRedirect(route('admin.stock-import.index'))
            ->assertSessionHas('error');
    }

    public function test_preview_rejects_a_file_without_required_columns(): void
    {
        Storage::fake('local');

        $this->actingAs($this->staff())
            ->post('/admin/stock-import/preview', ['file' => $this->upload("foo,bar\n1,2\n")])
            ->assertSessionHas('error');
    }
}
