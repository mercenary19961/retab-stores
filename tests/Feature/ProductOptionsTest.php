<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product options (sizes / packaging) through the money path: the chosen option
 * prices the line, is snapshotted onto the order, and deducts the right number
 * of base stock units on confirmation.
 */
class ProductOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::create(['name_ar' => 'تمور', 'slug' => 'dates']);
        $product = Product::create([
            'category_id' => $category->id, 'name_ar' => 'خلاص', 'slug' => 'khalas',
            'price' => 5, 'sku' => 'K1', 'smacc_sku' => 'SM1', 'stock' => 100,
        ]);
        $product->options()->createMany([
            ['label_ar' => '250 جرام', 'amount' => 250, 'price' => 5, 'stock_units' => 1, 'sort_order' => 1],
            ['label_ar' => '500 جرام', 'amount' => 500, 'price' => 10, 'stock_units' => 2, 'sort_order' => 2],
            ['label_ar' => 'كرتون', 'amount' => null, 'is_box' => true, 'price' => 69, 'stock_units' => 20, 'sort_order' => 3],
        ]);

        return $product;
    }

    public function test_listing_price_is_the_cheapest_option(): void
    {
        $p = $this->product();
        $this->assertTrue($p->hasOptions());
        $this->assertSame(5.0, $p->effectivePrice()); // "from 5"
    }

    public function test_a_product_without_options_is_unchanged(): void
    {
        $category = Category::create(['name_ar' => 'تمور', 'slug' => 'd2']);
        $plain = Product::create([
            'category_id' => $category->id, 'name_ar' => 'عجوة', 'slug' => 'ajwa',
            'price' => 30, 'sku' => 'A1', 'smacc_sku' => 'SM2', 'stock' => 10,
        ]);
        $this->assertFalse($plain->hasOptions());
        $this->assertSame(30.0, $plain->effectivePrice());
    }

    public function test_adding_an_option_prices_the_line_and_keeps_options_separate(): void
    {
        $p = $this->product();
        $carton = $p->options()->where('amount', null)->first();
        $half = $p->options()->where('amount', 500)->first();

        $cart = app(CartService::class);
        $cart->add($p, 1, $carton);
        $cart->add($p, 2, $half);
        $cart->add($p, 1, $carton); // same option → bumps the existing line

        $summary = $cart->summary();
        $this->assertCount(2, $summary['items']);          // two distinct options
        $this->assertEqualsCanonicalizing(
            [['كرتون', 2, 69.0], ['500 جرام', 2, 10.0]],
            $summary['items']->map(fn ($i) => [$i['option_label_ar'], $i['quantity'], $i['unit_price']])->all(),
        );
    }

    public function test_order_snapshots_the_option_and_confirmation_deducts_by_units(): void
    {
        $p = $this->product();
        $carton = $p->options()->where('amount', null)->first(); // stock_units 20

        $cart = Cart::create(['session_token' => 'g1']);
        $cart->items()->create([
            'product_id' => $p->id, 'product_option_id' => $carton->id, 'quantity' => 2, 'unit_price' => 69,
        ]);

        $order = app(CheckoutService::class)->placeOrder(
            $cart, ['name' => 'Zaid', 'phone' => '+966500000000'], ['country' => 'SA', 'city' => 'Riyadh'],
        );

        $item = $order->items->first();
        $this->assertSame('كرتون', $item->option_label_ar);
        $this->assertEquals(69.0, (float) $item->unit_price);
        $this->assertSame(20, $item->stock_units);
        $this->assertEquals(138.0, (float) $item->line_total);

        // Confirm → deduct stock_units × quantity = 20 × 2 = 40 (from 100).
        $order->forceFill(['status' => OrderStatus::AwaitingConfirmation])->save();
        app(OrderConfirmationService::class)->confirm(Order::find($order->id));

        $this->assertSame(60, $p->fresh()->stock);
    }

    public function test_a_discount_reaches_the_sizes_only_when_opted_in(): void
    {
        $p = $this->product(); // price 5, options 5 / 10 / carton 69
        $p->update(['sale_price' => 4]); // 20% off, but NOT opted into sizes yet
        $half = $p->options()->where('amount', 500)->first();  // regular 10

        // Default: the discount is on the original price only — sizes are unaffected.
        $this->assertSame(10.0, $p->optionEffectivePrice($half));
        // "From" = the cheapest buyable = the ORIGINAL discounted (5 → 4).
        $this->assertSame(4.0, $p->effectivePrice());
        app(CartService::class)->add($p, 1, $half);
        $this->assertEquals(10.0, (float) app(CartService::class)->summary()['items']->first()['unit_price']);

        // Opt in → every size is discounted by the same ratio (×0.8).
        $p->update(['sale_applies_to_options' => true]);
        $carton = $p->options()->where('amount', null)->first(); // regular 69
        $this->assertSame(8.0, $p->fresh()->optionEffectivePrice($half));
        $this->assertSame(55.2, $p->fresh()->optionEffectivePrice($carton));
        $this->assertSame(4.0, $p->fresh()->effectivePrice()); // cheapest (5) discounted
    }

    public function test_the_original_product_is_still_buyable_when_it_has_sizes(): void
    {
        $p = $this->product(); // price 5, plus size options
        $p->update(['sale_price' => 4]); // discount is on the original

        // No option = the ORIGINAL, at the (discounted) original price.
        $this->assertSame(4.0, $p->priceForOption(null));

        app(CartService::class)->add($p, 1, null); // keep the default
        $item = app(CartService::class)->summary()['items']->first();
        $this->assertNull($item['option_id']);
        $this->assertEquals(4.0, (float) $item['unit_price']);
    }

    public function test_checkout_rejects_a_deactivated_option(): void
    {
        $p = $this->product();
        $half = $p->options()->where('amount', 500)->first();

        $cart = Cart::create(['session_token' => 'g2']);
        $cart->items()->create(['product_id' => $p->id, 'product_option_id' => $half->id, 'quantity' => 1, 'unit_price' => 10]);

        $half->update(['is_active' => false]);

        $this->expectException(\RuntimeException::class);
        app(CheckoutService::class)->placeOrder(
            $cart, ['name' => 'Zaid', 'phone' => '+966500000000'], ['country' => 'SA', 'city' => 'Riyadh'],
        );
    }

    public function test_the_box_pack_count_reaches_the_product_page_only_when_it_is_worth_showing(): void
    {
        // 🔑 The shopper-facing "pieces in a box" note IS stock_units, not a second
        // column, so the number a customer reads and the number a box sale deducts
        // can never drift apart.
        $product = $this->product();
        // The publish guard hides a product with no image, which would 404 here.
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/x.jpg', 'is_primary' => true]);
        $product->update(['is_active' => true]);

        $options = $this->get(route('shop.product', $product->slug))
            ->assertSuccessful()
            ->viewData('page')['props']['product']['options'];

        $byLabel = collect($options)->keyBy('label_ar');

        // The carton consumes 20 base units, so that is what the page is told.
        $this->assertSame(20, $byLabel['كرتون']['pieces']);

        // A weight option is not a box: its stock_units is an inventory figure,
        // never a pack count, so it must not be advertised as one.
        $this->assertNull($byLabel['500 جرام']['pieces']);
        $this->assertNull($byLabel['250 جرام']['pieces']);
    }

    public function test_a_box_left_at_one_unit_shows_no_pack_count(): void
    {
        // This is what makes the note optional: 1 is the default for a new box and
        // means the count was never entered, and "contains 1 pack" tells nobody
        // anything. No extra toggle column needed.
        $product = $this->product();
        // The publish guard hides a product with no image, which would 404 here.
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/x.jpg', 'is_primary' => true]);
        $product->update(['is_active' => true]);
        $product->options()->where('label_ar', 'كرتون')->update(['stock_units' => 1]);

        $options = $this->get(route('shop.product', $product->slug))
            ->assertSuccessful()
            ->viewData('page')['props']['product']['options'];

        $this->assertNull(collect($options)->firstWhere('label_ar', 'كرتون')['pieces']);
    }

    public function test_the_base_choice_is_named_by_its_size_when_the_product_declares_one(): void
    {
        // 🔑 Without this the picker's default choice reads «الأصلي», which tells a
        // shopper nothing — the reason the weight had to stay in the product name.
        $product = $this->product();
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/x.jpg', 'is_primary' => true]);
        $product->update(['is_active' => true, 'base_weight_grams' => 250]);

        $props = $this->get(route('shop.product', $product->slug))
            ->assertSuccessful()
            ->viewData('page')['props']['product'];

        $this->assertSame(250, $props['base_weight_grams']);
    }

    public function test_a_product_with_no_declared_size_ships_null_and_keeps_the_generic_label(): void
    {
        // Null is what makes the field optional: plenty of products (nuts, boxes,
        // pastes) have no single meaningful weight, and they must keep working.
        $product = $this->product();
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/y.jpg', 'is_primary' => true]);
        $product->update(['is_active' => true]);

        $props = $this->get(route('shop.product', $product->slug))
            ->assertSuccessful()
            ->viewData('page')['props']['product'];

        $this->assertNull($props['base_weight_grams']);
    }
}
