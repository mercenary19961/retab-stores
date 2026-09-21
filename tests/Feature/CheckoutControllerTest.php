<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
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

    // ---- Phone number ---------------------------------------------------------

    /**
     * 🔴 Client-reported: the phone field accepted anything. `4343443434` is the
     * literal value from that report — ten digits, no country code, not a Saudi
     * mobile. It reached an order, and that order could never be confirmed by
     * WhatsApp nor delivered by a courier who rings ahead.
     */
    public function test_a_phone_that_is_not_a_real_number_is_refused(): void
    {
        $this->seedCartWithOneProduct();

        $this->post('/checkout', [
            'customer_name' => 'Zaid',
            'customer_phone' => '4343443434',
            'country' => 'SA',
            'city' => 'Riyadh',
            'payment_method' => 'bank_transfer',
        ])->assertSessionHasErrors('customer_phone');

        $this->assertDatabaseCount('orders', 0);
    }

    /** A half-typed number must not slip through either. */
    public function test_a_partial_phone_is_refused(): void
    {
        $this->seedCartWithOneProduct();
        $this->placeOrder(['customer_phone' => '05123']);

        $this->assertDatabaseCount('orders', 0);
    }

    /** The shapes customers actually type all still work. */
    public function test_the_usual_ways_of_writing_a_saudi_mobile_are_accepted(): void
    {
        foreach (['0512345678', '512345678', '050 123 4567', '+966512345678'] as $i => $phone) {
            $this->seedCartWithOneProduct();
            $this->placeOrder(['customer_phone' => $phone]);

            $this->assertSame($i + 1, Order::count(), "[{$phone}] should have been accepted");
        }
    }

    /**
     * The alternate recipient is the person the courier actually rings, so an
     * unreachable number there is the same failure one step later.
     */
    public function test_an_alternate_recipient_phone_is_validated_too(): void
    {
        $this->seedCartWithOneProduct();
        $this->placeOrder(['recipient_name' => 'Sara', 'recipient_phone' => '4343443434']);

        $this->assertDatabaseCount('orders', 0);
    }

    // ---- Address: national address code + saving it to the account -----------

    /** @param  array<string,mixed>  $overrides */
    private function placeOrder(array $overrides = []): void
    {
        $this->post('/checkout', [
            'customer_name' => 'Zaid',
            'customer_phone' => '0512345678',
            'country' => 'SA',
            'city' => 'Riyadh',
            'district' => 'Al Malqa',
            'street' => 'King Fahd Road',
            'payment_method' => 'bank_transfer',
            ...$overrides,
        ]);
    }

    /**
     * 🔑 Customers type the code as "RRMD 7708" or "rrmd-7708". Normalising
     * before validation is what stops the shape rule rejecting the same address
     * written a different way.
     */
    public function test_a_national_address_is_normalised_before_it_is_validated(): void
    {
        $this->seedCartWithOneProduct();
        $this->placeOrder(['short_address' => 'rrmd 7708']);

        $this->assertSame('RRMD7708', Order::firstOrFail()->shipping_address['short_address']);
    }

    public function test_a_misshapen_national_address_is_refused(): void
    {
        $this->seedCartWithOneProduct();
        $this->placeOrder(['short_address' => 'ABC123']);

        $this->assertDatabaseCount('orders', 0);
    }

    /** Optional by design — most customers do not know theirs. */
    public function test_an_order_can_be_placed_without_a_national_address(): void
    {
        $this->seedCartWithOneProduct();
        $this->placeOrder();

        $this->assertDatabaseCount('orders', 1);
        $this->assertNull(Order::firstOrFail()->shipping_address['short_address']);
    }

    public function test_a_signed_in_customer_can_save_the_address_to_their_account(): void
    {
        $user = User::forceCreate(['name' => 'Zaid', 'email' => 'z@test.com', 'password' => bcrypt('x'), 'role' => 'customer']);
        $this->actingAs($user);
        $this->seedCartWithOneProduct();
        $this->placeOrder(['save_address' => '1', 'short_address' => 'RRMD7708']);

        $this->assertSame(1, $user->addresses()->count());
        $address = $user->addresses()->first();
        $this->assertSame('RRMD7708', $address->short_address);
        // First address saved becomes the default, so a single-address account
        // never has to choose.
        $this->assertTrue($address->is_default);
    }

    public function test_the_address_is_not_saved_when_the_customer_did_not_ask(): void
    {
        $user = User::forceCreate(['name' => 'Zaid', 'email' => 'z2@test.com', 'password' => bcrypt('x'), 'role' => 'customer']);
        $this->actingAs($user);
        $this->seedCartWithOneProduct();
        $this->placeOrder(['save_address' => '0']);

        $this->assertSame(0, $user->addresses()->count());
    }

    /**
     * Ordering to the same place twice must not fill the picker with duplicates
     * — which is why the save is deduplicated on the address, not the tick-box.
     */
    public function test_ordering_twice_to_the_same_address_saves_it_once(): void
    {
        $user = User::forceCreate(['name' => 'Zaid', 'email' => 'z3@test.com', 'password' => bcrypt('x'), 'role' => 'customer']);
        $this->actingAs($user);

        foreach ([1, 2] as $ignored) {
            $this->seedCartWithOneProduct();
            $this->placeOrder(['save_address' => '1', 'short_address' => 'RRMD7708']);
        }

        $this->assertSame(2, Order::count());
        $this->assertSame(1, $user->addresses()->count());
    }

    /** A collection order has no address, so there is nothing to remember. */
    public function test_a_collection_order_saves_no_address(): void
    {
        $user = User::forceCreate(['name' => 'Zaid', 'email' => 'z4@test.com', 'password' => bcrypt('x'), 'role' => 'customer']);
        $this->actingAs($user);
        $this->seedCartWithOneProduct();
        $this->placeOrder(['fulfillment' => 'collection', 'save_address' => '1', 'country' => null, 'city' => null]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(0, $user->addresses()->count());
    }

    public function test_saved_addresses_are_offered_on_the_checkout_page(): void
    {
        $user = User::forceCreate(['name' => 'Zaid', 'email' => 'z5@test.com', 'password' => bcrypt('x'), 'role' => 'customer']);
        $user->addresses()->create([
            'country' => 'SA', 'city' => 'Riyadh', 'district' => 'Al Malqa',
            'short_address' => 'rrmd 7708', 'is_default' => true,
        ]);
        $this->actingAs($user);
        $this->seedCartWithOneProduct();

        $this->get('/checkout')->assertInertia(
            fn (Assert $page) => $page
                ->has('savedAddresses', 1)
                // Stored canonical, whatever was typed — see the model mutator.
                ->where('savedAddresses.0.short_address', 'RRMD7708'),
        );
    }

    /** Guests have no account, so there is nothing to offer. */
    public function test_guests_are_offered_no_saved_addresses(): void
    {
        $this->seedCartWithOneProduct();

        $this->get('/checkout')->assertInertia(fn (Assert $page) => $page->has('savedAddresses', 0));
    }
}
