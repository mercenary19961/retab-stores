<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderPlacedMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CustomerMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Customer-facing transactional email: who gets one, in which language, and what
 * the bank-transfer receipt actually contains.
 */
class CustomerEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(CheckoutService::SHIPPING_FEE_KEY, 25);
        Setting::set('store_name_ar', 'رطاب للتمور');
        Setting::set('store_name_en', 'Retab Dates');
        Setting::set('store_phone', '+966550883845');
        Setting::set('bank_name', 'مصرف الراجحي');
        Setting::set('bank_beneficiary', 'شركة مصنع رطاب الوطن للتمور');
        Setting::set('bank_account', '145608010008130');
        Setting::set('bank_iban', 'SA9780000145608010008130');
    }

    private function seedCart(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'التمور', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'name_en' => 'Sukkari',
            'slug' => 'sukkari-' . uniqid(),
            'price' => 50,
            'sku' => 'SK-' . uniqid(),
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);

        return $product;
    }

    private function checkout(array $overrides = []): void
    {
        $this->post('/checkout', array_merge([
            'customer_name' => 'Zaid',
            'customer_email' => 'zaid@example.com',
            'customer_phone' => '+966500000000',
            'country' => 'SA',
            'city' => 'Riyadh',
            'payment_method' => 'bank_transfer',
        ], $overrides));
    }

    private function makeOrder(array $overrides = []): Order
    {
        $category = Category::create(['name_ar' => 'تمور', 'slug' => 'd-' . uniqid()]);
        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'name_en' => 'Sukkari',
            'slug' => 'p-' . uniqid(),
            'price' => 50,
            'sku' => 'SK-' . uniqid(),
            'stock' => 100,
        ]);

        $order = Order::create(array_merge([
            'order_number' => 'RTB-' . uniqid(),
            'customer_name' => 'Zaid',
            'customer_email' => 'zaid@example.com',
            'customer_phone' => '+966500000000',
            'locale' => 'ar',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'subtotal' => 150,
            'shipping_fee' => 25,
            'total' => 175,
        ], $overrides));

        $order->items()->create([
            'product_id' => $product->id,
            'product_name_ar' => $product->name_ar,
            'product_name_en' => $product->name_en,
            'sku' => $product->sku,
            'unit_price' => 50,
            'quantity' => 3,
            'line_total' => 150,
        ]);

        return $order;
    }

    public function test_bank_transfer_checkout_queues_the_receipt_to_the_customer(): void
    {
        Mail::fake();
        $this->seedCart();

        $this->checkout();

        Mail::assertQueued(OrderPlacedMail::class, fn ($mail) => $mail->hasTo('zaid@example.com'));
    }

    /**
     * `customer_email` is nullable by design (phone-only accounts and guest
     * checkout only require a phone) — those customers are reached over WhatsApp.
     */
    public function test_no_email_is_queued_when_the_customer_gave_no_address(): void
    {
        Mail::fake();
        $this->seedCart();

        $this->checkout(['customer_email' => null]);

        $this->assertDatabaseCount('orders', 1);
        Mail::assertNothingQueued();
    }

    public function test_checkout_snapshots_the_locale_the_customer_ordered_in(): void
    {
        $this->seedCart();
        $this->post('/locale/en');

        $this->checkout();

        $this->assertSame('en', Order::firstOrFail()->locale);
    }

    /**
     * The core reason `orders.locale` exists: these mails are QUEUED, so without
     * the snapshot they would render in the worker's locale (AR) and an English
     * shopper would get an Arabic receipt.
     */
    public function test_the_receipt_renders_in_the_orders_locale_not_the_app_locale(): void
    {
        $english = $this->makeOrder(['locale' => 'en']);
        $arabic = $this->makeOrder(['locale' => 'ar']);

        app()->setLocale('ar'); // the worker's locale — must NOT decide the language

        $rendered = (new OrderPlacedMail($english))->render();
        $this->assertStringContainsString('Thank you for your order', $rendered);
        $this->assertStringContainsString('dir="ltr"', $rendered);
        $this->assertStringContainsString('Sukkari', $rendered); // EN item snapshot

        app()->setLocale('en'); // and neither does the opposite

        $rendered = (new OrderPlacedMail($arabic))->render();
        $this->assertStringContainsString('شكراً لطلبك', $rendered);
        $this->assertStringContainsString('dir="rtl"', $rendered);
        $this->assertStringContainsString('سكري', $rendered);
    }

    /**
     * ⚠️ Asserted through `assertHasSubject`, NOT by calling `envelope()` directly.
     * Laravel applies `$this->locale` by wrapping delivery in `withLocale()` —
     * reaching past that into `envelope()` yourself resolves `__()` in the ambient
     * locale and makes a correctly-localized subject look broken.
     */
    public function test_the_subject_line_also_follows_the_orders_locale(): void
    {
        app()->setLocale('ar');

        (new OrderPlacedMail($this->makeOrder(['locale' => 'en', 'order_number' => 'RTB-1234'])))
            ->assertHasSubject('We received your order RTB-1234');

        app()->setLocale('en');

        (new OrderPlacedMail($this->makeOrder(['locale' => 'ar', 'order_number' => 'RTB-5678'])))
            ->assertHasSubject('استلمنا طلبك RTB-5678');
    }

    public function test_bank_transfer_receipt_carries_the_iban_and_the_reference(): void
    {
        $order = $this->makeOrder([
            'order_number' => 'RTB-9001',
            'payment_method' => PaymentMethod::BankTransfer,
            'payment_status' => PaymentStatus::Pending,
            'status' => OrderStatus::PendingPayment,
        ]);

        $rendered = (new OrderPlacedMail($order))->render();

        $this->assertStringContainsString('SA9780000145608010008130', $rendered);
        $this->assertStringContainsString('145608010008130', $rendered);
        $this->assertStringContainsString('RTB-9001', $rendered); // transfer reference
    }

    /** A paid card order has nothing to transfer — the block must not render. */
    public function test_paid_orders_do_not_show_transfer_instructions(): void
    {
        $rendered = (new OrderPlacedMail($this->makeOrder()))->render();

        $this->assertStringNotContainsString('SA9780000145608010008130', $rendered);
    }

    /**
     * The storefront gates /orders/{number} on session state, so a guest link
     * opened later would 403 — better no button than a broken one.
     */
    public function test_the_order_link_is_only_offered_to_registered_customers(): void
    {
        $guestOrder = $this->makeOrder(['order_number' => 'RTB-GUEST']);
        $this->assertStringNotContainsString(route('orders.show', 'RTB-GUEST'), (new OrderPlacedMail($guestOrder))->render());

        $user = User::factory()->create();
        $userOrder = $this->makeOrder(['order_number' => 'RTB-USER', 'user_id' => $user->id]);
        $this->assertStringContainsString(route('orders.show', 'RTB-USER'), (new OrderPlacedMail($userOrder))->render());
    }

    public function test_admin_confirmation_emails_the_customer(): void
    {
        Mail::fake();
        $order = $this->makeOrder();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post("/admin/orders/{$order->order_number}/confirm")
            ->assertRedirect();

        Mail::assertQueued(OrderConfirmedMail::class, fn ($mail) => $mail->hasTo('zaid@example.com'));
    }

    /** Falls back to the account address when the order snapshot has none. */
    public function test_the_account_address_is_used_when_the_order_has_none(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'account@example.com']);
        $order = $this->makeOrder(['customer_email' => null, 'user_id' => $user->id]);

        $this->assertTrue(app(CustomerMailer::class)->orderPlaced($order));
        Mail::assertQueued(OrderPlacedMail::class, fn ($mail) => $mail->hasTo('account@example.com'));
    }
}
