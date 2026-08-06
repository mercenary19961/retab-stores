<?php

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Http\Controllers\CartController;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartControllerTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'التمور', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'slug' => 'sukkari-'.uniqid(),
            'price' => 50,
            'sku' => 'SK-'.uniqid(),
            'stock' => 10,
            'is_active' => true,
        ]);
    }

    public function test_add_to_cart_creates_an_item(): void
    {
        $product = $this->product();

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_adding_same_product_increments_quantity(): void
    {
        $product = $this->product();

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 3]);
    }

    public function test_cart_page_renders(): void
    {
        $this->get('/cart')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('shop/cart')->has('items'),
        );
    }

    public function test_remove_item(): void
    {
        $product = $this->product();
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $item = CartItem::firstOrFail();

        $this->delete("/cart/items/{$item->id}")->assertRedirect();

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_cart_ships_the_money_breakdown_so_shipping_is_not_a_surprise(): void
    {
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, 25);
        $product = $this->product(); // 50 SAR
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);

        $this->get('/cart')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('subtotal', 100)
                ->where('shippingFee', 25)
                ->where('discount', 0)
                ->where('total', 125),
        );
    }

    /** The empty state gets recommendations; a filled cart must not pay for that query. */
    public function test_best_sellers_are_only_loaded_for_an_empty_cart(): void
    {
        $product = $this->product();

        $this->get('/cart')->assertOk()->assertInertia(fn (Assert $page) => $page->has('bestSellers'));

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $this->get('/cart')->assertOk()->assertInertia(fn (Assert $page) => $page->count('bestSellers', 0));
    }

    public function test_cart_items_include_a_product_image(): void
    {
        $product = $this->product();
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        // No image uploaded → null (the UI falls back to a placeholder), but the
        // KEY must exist or the page would read `undefined`.
        $this->get('/cart')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('items.0.image'),
        );
    }

    public function test_a_valid_coupon_applies_a_discount_and_carries_into_checkout(): void
    {
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, 25);
        $product = $this->product(); // 50
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]); // subtotal 100

        Coupon::create([
            'code' => 'SAVE10',
            'type' => CouponType::Percentage,
            'value' => 10,
            'is_active' => true,
        ]);

        $this->post('/cart/coupon', ['code' => 'SAVE10'])->assertRedirect();

        $this->get('/cart')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('discount', 10)
                ->where('total', 115) // 100 − 10 + 25
                ->where('coupon.code', 'SAVE10'),
        );

        // Pre-filled at checkout so the shopper doesn't type it twice.
        $this->get('/checkout')->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('appliedCoupon', 'SAVE10'),
        );
    }

    public function test_an_invalid_coupon_is_rejected_and_not_stored(): void
    {
        $product = $this->product();
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/cart/coupon', ['code' => 'NOPE'])->assertRedirect()->assertSessionHas('error');

        $this->assertNull(session(CartController::COUPON_SESSION_KEY));
        $this->get('/cart')->assertOk()->assertInertia(fn (Assert $page) => $page->where('discount', 0));
    }

    /**
     * The cart is mutable, so a coupon valid when applied can stop qualifying.
     * It must be dropped on re-render rather than shown as a discount the order
     * would refuse — this is the whole risk of previewing coupons on the cart.
     */
    public function test_a_coupon_that_stops_qualifying_is_dropped_on_render(): void
    {
        $product = $this->product(); // 50 each
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 4]); // subtotal 200

        Coupon::create([
            'code' => 'BIG200',
            'type' => CouponType::Fixed,
            'value' => 20,
            'min_order_total' => 200,
            'is_active' => true,
        ]);

        $this->post('/cart/coupon', ['code' => 'BIG200']);
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('discount', 20));

        // Drop to 100 — now under the coupon's 200 minimum.
        $item = CartItem::firstOrFail();
        $this->patch("/cart/items/{$item->id}", ['quantity' => 2]);

        $this->get('/cart')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('discount', 0)
                ->where('coupon', null)
                ->whereNot('couponError', null),
        );
        $this->assertNull(session(CartController::COUPON_SESSION_KEY), 'the stale code must not survive to checkout');
    }

    public function test_a_free_shipping_coupon_waives_the_fee_instead_of_discounting(): void
    {
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, 25);
        $product = $this->product();
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]); // 100

        Coupon::create([
            'code' => 'FREESHIP',
            'type' => CouponType::FreeShipping,
            'value' => 0,
            'is_active' => true,
        ]);

        $this->post('/cart/coupon', ['code' => 'FREESHIP']);

        $this->get('/cart')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('discount', 0)      // goods are not discounted
                ->where('shippingFee', 0)   // the fee is waived
                ->where('total', 100)
                ->where('coupon.waives_shipping', true),
        );
    }

    public function test_removing_a_coupon_clears_it(): void
    {
        $product = $this->product();
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);
        Coupon::create(['code' => 'SAVE10', 'type' => CouponType::Percentage, 'value' => 10, 'is_active' => true]);
        $this->post('/cart/coupon', ['code' => 'SAVE10']);

        $this->delete('/cart/coupon')->assertRedirect();

        $this->assertNull(session(CartController::COUPON_SESSION_KEY));
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('coupon', null));
    }
}
