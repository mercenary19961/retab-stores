<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    private function seedCartWithOneProduct(): Product
    {
        Setting::set(CheckoutService::SHIPPING_FEE_KEY, 25);
        Setting::set('bank_name', 'مصرف الراجحي');
        Setting::set('bank_beneficiary', 'شركة مصنع رطاب الوطن للتمور');
        Setting::set('bank_account', '145608010008130');
        Setting::set('bank_iban', 'SA9780000145608010008130');

        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'التمور', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'slug' => 'sukkari-'.uniqid(),
            'price' => 50,
            'sku' => 'SK-'.uniqid(),
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);

        return $product;
    }

    public function test_bank_transfer_checkout_places_order_and_clears_cart(): void
    {
        $this->seedCartWithOneProduct();

        $response = $this->post('/checkout', [
            'customer_name' => 'Zaid',
            'customer_phone' => '+966500000000',
            'country' => 'SA',
            'city' => 'Riyadh',
            'payment_method' => 'bank_transfer',
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('orders.show', $order->order_number));

        $this->assertSame('bank_transfer', $order->payment_method->value);
        $this->assertSame('pending_payment', $order->status->value);
        $this->assertEquals(125.0, (float) $order->total); // 100 + 25 flat shipping
        $this->assertDatabaseCount('cart_items', 0); // cart cleared
    }

    public function test_checkout_requires_core_fields(): void
    {
        $this->seedCartWithOneProduct();

        $this->post('/checkout', [])
            ->assertSessionHasErrors(['customer_name', 'customer_phone', 'country', 'city', 'payment_method']);
    }

    public function test_order_confirmation_shows_bank_details_for_placed_order(): void
    {
        $this->seedCartWithOneProduct();
        $this->post('/checkout', [
            'customer_name' => 'Zaid',
            'customer_phone' => '+966500000000',
            'country' => 'SA',
            'city' => 'Riyadh',
            'payment_method' => 'bank_transfer',
        ]);

        $order = Order::firstOrFail();

        $this->get(route('orders.show', $order->order_number))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('shop/order-confirmation')
                    ->where('order.order_number', $order->order_number)
                    ->where('bank.iban', 'SA9780000145608010008130'),
            );
    }

    // ---- Which payment methods the store offers -----------------------------

    public function test_checkout_offers_every_method_when_nothing_has_been_configured(): void
    {
        $this->seedCartWithOneProduct();

        $this->get('/checkout')->assertInertia(
            fn (Assert $page) => $page->where('paymentMethods', ['card', 'tamara', 'bank_transfer']),
        );
    }

    public function test_a_disabled_method_is_not_offered_at_checkout(): void
    {
        $this->seedCartWithOneProduct();
        Setting::set(PaymentMethod::Card->settingKey(), '0');
        Setting::set(PaymentMethod::Tamara->settingKey(), '0');

        $this->get('/checkout')->assertInertia(
            fn (Assert $page) => $page->where('paymentMethods', ['bank_transfer']),
        );
    }

    /**
     * 🔴 The real control. Hiding a radio button in the browser stops nobody
     * posting the value by hand, so the request has to refuse it too.
     */
    public function test_a_disabled_method_is_refused_even_when_posted_directly(): void
    {
        $this->seedCartWithOneProduct();
        Setting::set(PaymentMethod::Card->settingKey(), '0');

        $this->post('/checkout', [
            'customer_name' => 'Zaid',
            'customer_phone' => '+966500000000',
            'country' => 'SA',
            'city' => 'Riyadh',
            'payment_method' => 'card',
        ])->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * Switching a gateway off must never strand a customer who already started
     * paying with it — that order stays payable, which is why pay() reads the
     * order's own method rather than the enabled list.
     */
    public function test_an_existing_order_keeps_its_method_after_that_method_is_switched_off(): void
    {
        $this->seedCartWithOneProduct();
        $this->post('/checkout', [
            'customer_name' => 'Zaid',
            'customer_phone' => '+966500000000',
            'country' => 'SA',
            'city' => 'Riyadh',
            'payment_method' => 'bank_transfer',
        ]);

        $order = Order::firstOrFail();
        Setting::set(PaymentMethod::BankTransfer->settingKey(), '0');

        $this->get(route('orders.show', $order->order_number))->assertOk();
        $this->assertSame(PaymentMethod::BankTransfer, $order->fresh()->payment_method);
    }
}
