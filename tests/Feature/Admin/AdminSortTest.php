<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSortTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seedData(): void
    {
        $cat = Category::create(['name_ar' => 'تمور', 'slug' => 'c-' . uniqid()]);
        Product::create([
            'category_id' => $cat->id, 'name_ar' => 'سكري', 'slug' => 'p-' . uniqid(),
            'price' => 50, 'sku' => 'SK-' . uniqid(), 'smacc_sku' => 'SM-1', 'stock' => 10,
        ]);
        $order = Order::create([
            'order_number' => 'RTB-' . uniqid(), 'customer_name' => 'زيد', 'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA'], 'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid, 'subtotal' => 50, 'total' => 50,
        ]);
        OrderReturn::create(['order_id' => $order->id, 'status' => ReturnStatus::Requested, 'reason' => 'تالف']);
        User::factory()->create(['role' => 'customer']);
    }

    /** Every whitelisted sort column (incl. the join-based ones) must not error. */
    public function test_all_sortable_columns_respond(): void
    {
        $this->seedData();
        $this->actingAs($this->staff());

        $map = [
            '/admin/products' => ['name_ar', 'sku', 'smacc_sku', 'category', 'price', 'stock', 'is_active'],
            '/admin/orders' => ['order_number', 'customer_name', 'status', 'payment_status', 'total', 'created_at'],
            '/admin/returns' => ['id', 'order_number', 'customer_name', 'status', 'created_at'],
            '/admin/customers' => ['name', 'phone', 'email', 'whatsapp_opt_in', 'confirmed_purchases_count', 'created_at'],
        ];

        foreach ($map as $url => $cols) {
            foreach ($cols as $col) {
                foreach (['asc', 'desc'] as $dir) {
                    $this->get("{$url}?sort={$col}&direction={$dir}")
                        ->assertOk(); // a SQL ambiguity/bad-join would 500 here
                }
            }
        }
    }

    /** The virtual 'category' sort orders products by the joined category name. */
    public function test_products_sort_by_category_name(): void
    {
        $catFirst = Category::create(['name_ar' => 'أجود التمور', 'slug' => 'a-' . uniqid()]);  // أ
        $catSecond = Category::create(['name_ar' => 'بلح فاخر', 'slug' => 'b-' . uniqid()]);      // ب
        Product::create(['category_id' => $catSecond->id, 'name_ar' => 'ب', 'slug' => 'pb', 'price' => 10, 'sku' => 'PB', 'stock' => 1]);
        Product::create(['category_id' => $catFirst->id, 'name_ar' => 'أ', 'slug' => 'pa', 'price' => 10, 'sku' => 'PA', 'stock' => 1]);

        $this->actingAs($this->staff())
            ->get('/admin/products?sort=category&direction=asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.data.0.category.name_ar', 'أجود التمور')
                ->where('products.data.1.category.name_ar', 'بلح فاخر'));
    }

    /** The category options a product can be assigned to are LEAF categories only —
     *  the top-level nav groups (parent_id null) are excluded, so no duplicate. */
    public function test_category_options_exclude_parent_nav_groups(): void
    {
        $parent = Category::create(['name_ar' => 'التمور', 'name_en' => 'Dates', 'slug' => 'cat-dates-x']);
        $leaf = Category::create(['name_ar' => 'التمور', 'name_en' => 'Dates', 'slug' => 'dates-x', 'parent_id' => $parent->id]);

        $this->actingAs($this->staff())->get('/admin/products')->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('categories', function ($cats) use ($parent, $leaf) {
                $ids = collect($cats)->pluck('id');

                return $ids->contains($leaf->id) && ! $ids->contains($parent->id);
            }),
        );
    }
}
