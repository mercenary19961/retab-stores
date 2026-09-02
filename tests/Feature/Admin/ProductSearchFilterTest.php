<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Searching the admin product list by SKU, and how an active category filter
 * silently narrows it.
 */
class ProductSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $dates;

    private Category $gifts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'a@retab.test', 'password' => bcrypt('x'), 'role' => 'admin',
        ]);
        $this->dates = Category::create(['name_ar' => 'تمور فاخرة', 'slug' => 'dates']);
        $this->gifts = Category::create(['name_ar' => 'هدايا المناسبات', 'slug' => 'occasion-gifts']);

        Product::create(['category_id' => $this->dates->id, 'name_ar' => 'خلاص', 'slug' => 'a', 'price' => 10, 'sku' => 'RTB-0007', 'stock' => 1]);
        Product::create(['category_id' => $this->gifts->id, 'name_ar' => 'عرض', 'slug' => 'b', 'price' => 240, 'sku' => 'RTB-0068', 'stock' => 1]);
    }

    private function skus(array $query): array
    {
        return collect($this->actingAs($this->admin)->get('/admin/products?'.http_build_query($query))
            ->assertSuccessful()
            ->viewData('page')['props']['products']['data'])
            ->pluck('sku')->all();
    }

    public function test_searching_by_sku_finds_the_product(): void
    {
        // The search predicate does cover SKU, so this half was never broken.
        $this->assertSame(['RTB-0068'], $this->skus(['search' => 'RTB-0068']));
    }

    public function test_an_active_category_filter_silently_hides_a_matching_sku(): void
    {
        // 🔴 The reported "search can't find it": the product list keeps the
        // category filter when you search, so an exact SKU in a DIFFERENT
        // category returns nothing — with no indication that a filter caused it.
        $this->assertSame([], $this->skus([
            'search' => 'RTB-0068',
            'category' => $this->dates->id,
        ]));

        // Same search, filter cleared, and it is found.
        $this->assertSame(['RTB-0068'], $this->skus(['search' => 'RTB-0068']));
    }

    public function test_the_page_reports_which_filters_are_active(): void
    {
        // The empty state needs this to explain itself instead of claiming there
        // are simply no products.
        $filters = $this->actingAs($this->admin)
            ->get('/admin/products?'.http_build_query(['search' => 'RTB-0068', 'category' => $this->dates->id]))
            ->viewData('page')['props']['filters'];

        $this->assertSame('RTB-0068', $filters['search']);
        $this->assertSame($this->dates->id, $filters['category']);
    }
}
