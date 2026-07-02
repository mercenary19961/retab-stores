<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_places_an_order_with_coupon_and_flat_shipping(): void
    {
        $category = Category::create(['name_ar' => 'تمور', 'slug' => 'dates']);
        $sukkari = Product::create([
            'category_id' => $category->id, 'name_ar' => 'سكري', 'slug' => 'sukkari',
            'price' => 50, 'sku' => 'SK1', 'smacc_sku' => 'SM1', 'stock' => 100,
        ]);
        $ajwa = Product::create([
            'category_id' => $category->id, 'name_ar' => 'عجوة', 'slug' => 'ajwa',
            'price' => 30, 'sku' => 'SK2', 'smacc_sku' => 'SM2', 'stock' => 100,
        ]);

        Coupon::create(['code' => 'SAVE10', 'type' => 'percentage', 'value' => 10, 'is_active' => true]);
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, 25);

        $cart = Cart::create(['session_token' => 'guest-1']);
        $cart->items()->create(['product_id' => $sukkari->id, 'quantity' => 2, 'unit_price' => 50]);
        $cart->items()->create(['product_id' => $ajwa->id, 'quantity' => 1, 'unit_price' => 30]);

        $order = app(CheckoutService::class)->placeOrder(
            $cart,
            ['name' => 'Zaid', 'phone' => '+966500000000'],
            ['country' => 'SA', 'city' => 'Riyadh'],
            'SAVE10',
        );

        // subtotal 130, 10% off = 13, + 25 flat shipping → 142
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertEquals(130.00, (float) $order->subtotal);
        $this->assertEquals(13.00, (float) $order->discount_total);
        $this->assertEquals(25.00, (float) $order->shipping_fee);
        $this->assertEquals(142.00, (float) $order->total);

        $this->assertCount(2, $order->items);
        $this->assertDatabaseHas('coupon_redemptions', [
            'order_id' => $order->id,
            'discount_amount' => 13.00,
        ]);
        $this->assertSame(1, Coupon::where('code', 'SAVE10')->first()->used_count);

        // Stock is NOT deducted at checkout (advisory until SMACC sync / admin confirm).
        $this->assertSame(100, $sukkari->fresh()->stock);
    }

    public function test_rejects_an_empty_cart(): void
    {
        $cart = Cart::create(['session_token' => 'guest-2']);

        $this->expectException(\RuntimeException::class);
        app(CheckoutService::class)->placeOrder($cart, ['name' => 'Zaid', 'phone' => '+966500000000'], ['country' => 'SA', 'city' => 'Riyadh']);
    }
}
