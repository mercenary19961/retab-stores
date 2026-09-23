<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderUnavailableMail;
use App\Mail\ReturnUpdateMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CustomerMailer;
use App\Services\ReturnService;
use App\Services\Shipping\ShippingService;
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
            'slug' => 'sukkari-'.uniqid(),
            'price' => 50,
            'sku' => 'SK-'.uniqid(),
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
        $category = Category::create(['name_ar' => 'تمور', 'slug' => 'd-'.uniqid()]);
        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'name_en' => 'Sukkari',
            'slug' => 'p-'.uniqid(),
            'price' => 50,
            'sku' => 'SK-'.uniqid(),
            'stock' => 100,
        ]);

        $order = Order::create(array_merge([
            'order_number' => 'RTB-'.uniqid(),
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
    /**
     * ⚠️ Runs with the require-email flag OFF, deliberately. Checkout now demands
     * an address while email is the only channel that reaches a customer — but
     * `orders.customer_email` is still nullable by design (the identity model
     * allows a phone-only account), so the mailer's no-op has to keep working for
     * the day that flag is turned back off. Testing it any other way would leave
     * the phone-only path uncovered the moment WhatsApp goes live.
     */
    public function test_no_email_is_queued_when_the_customer_gave_no_address(): void
    {
        config(['retab.require_customer_email' => false]);
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
            ->assertHasSubject('We received your order: Sukkari (RTB-1234)');

        app()->setLocale('en');

        (new OrderPlacedMail($this->makeOrder(['locale' => 'ar', 'order_number' => 'RTB-5678'])))
            ->assertHasSubject('استلمنا طلبك: سكري (RTB-5678)');
    }

    /**
     * The new emails follow the same rule, and the return one is worth pinning
     * separately: its subject is built by an overridden envelope(), so it does
     * not inherit the base class's behaviour for free.
     *
     * ⚠️ `assertHasSubject` again, never `envelope()` — the ambient locale is
     * deliberately set to the WRONG language here, which is exactly the
     * condition the queue worker renders in.
     */
    public function test_the_new_emails_follow_the_orders_locale_too(): void
    {
        app()->setLocale('en');

        $order = $this->makeOrder(['locale' => 'ar', 'order_number' => 'RTB-AR-1']);

        (new OrderUnavailableMail($order))->assertHasSubject('بخصوص طلبك: سكري (RTB-AR-1)');
        (new OrderDeliveredMail($order))->assertHasSubject('تم توصيل طلبك: سكري (RTB-AR-1)');

        $return = OrderReturn::create([
            'order_id' => $order->id,
            'status' => ReturnStatus::Approved,
            'reason' => 'Damaged',
        ]);

        (new ReturnUpdateMail($return))->assertHasSubject('طلب الإرجاع الخاص بك: تمت الموافقة (RTB-AR-1)');
    }

    /**
     * Rendering is its own assertion: a Blade typo or a missing view variable
     * only surfaces here, not in the queue assertions above. Both locales,
     * because the templates branch on direction.
     */
    public function test_the_new_templates_render_in_both_locales(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $order = $this->makeOrder(['locale' => $locale, 'order_number' => 'RTB-R-'.strtoupper($locale)]);
            $return = OrderReturn::create([
                'order_id' => $order->id,
                'status' => ReturnStatus::Refunded,
                'reason' => 'Damaged',
            ]);

            foreach ([new OrderUnavailableMail($order), new OrderDeliveredMail($order), new ReturnUpdateMail($return)] as $mail) {
                $html = $mail->render();

                $this->assertStringContainsString($locale === 'ar' ? 'dir="rtl"' : 'dir="ltr"', $html);
                // A missing key renders as the key itself, which no other
                // assertion here would notice.
                $this->assertDoesNotMatchRegularExpression('/emails\.[a-z_.]+/', $html, class_basename($mail)." [{$locale}]");
            }
        }
    }

    private function addItem(Order $order, string $nameAr, string $nameEn, float $lineTotal): void
    {
        $order->items()->create([
            'product_name_ar' => $nameAr,
            'product_name_en' => $nameEn,
            'sku' => 'SK-'.uniqid(),
            'unit_price' => $lineTotal,
            'quantity' => 1,
            'line_total' => $lineTotal,
        ]);
    }

    /**
     * With several products the subject names ONE (the highest line total, so the
     * headline is what the order is mostly about) and counts the rest; the body
     * still lists every item.
     */
    public function test_a_multi_item_subject_names_the_biggest_line_and_counts_the_rest(): void
    {
        $order = $this->makeOrder(['locale' => 'en', 'order_number' => 'RTB-MULTI']); // Sukkari, 150
        $this->addItem($order, 'خلاص', 'Khalas', 400);
        $this->addItem($order, 'عجوة', 'Ajwa', 90);

        $mail = new OrderConfirmedMail($order->fresh());
        $mail->assertHasSubject('Your order is confirmed: Khalas and 2 more items (RTB-MULTI)');

        $rendered = $mail->render();
        foreach (['Sukkari', 'Khalas', 'Ajwa'] as $name) {
            $this->assertStringContainsString($name, $rendered);
        }
    }

    /** Arabic has distinct forms for one, two, 3 to 10, and 11+ remaining products. */
    public function test_the_arabic_remainder_uses_the_right_plural_form(): void
    {
        $order = $this->makeOrder(); // Sukkari, 150: the lead line throughout

        $expected = [
            1 => 'سكري ومنتج آخر',
            2 => 'سكري ومنتجان آخران',
            3 => 'سكري و3 منتجات أخرى',
            11 => 'سكري و11 منتجًا آخر',
        ];

        $added = 0;
        foreach ($expected as $others => $summary) {
            for (; $added < $others; $added++) {
                $this->addItem($order, 'صنف', 'Item', 10);
            }

            $this->assertSame($summary, $order->fresh()->itemsSummary('ar'));
        }
    }

    /** An English name missing from the snapshot falls back to the Arabic one. */
    public function test_an_english_subject_falls_back_to_the_arabic_name(): void
    {
        $order = $this->makeOrder(['locale' => 'en']);
        $order->items()->update(['product_name_en' => null]);

        $this->assertSame('سكري', $order->fresh()->itemsSummary('en'));
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

    // ---------------------------------------------------------------------
    // The gaps that existed while WhatsApp is the only channel carrying them.
    // ---------------------------------------------------------------------

    /**
     * 🔴 The worst of the gaps. Until now this was WhatsApp-only, and WhatsApp is
     * unconfigured in production — so a customer whose order could not be filled
     * was refunded with no explanation on any channel at all.
     */
    public function test_marking_an_order_unavailable_emails_the_customer(): void
    {
        Mail::fake();
        $order = $this->makeOrder();
        $admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'a'.uniqid().'@test.com',
            'password' => bcrypt('x'), 'role' => 'admin',
        ]);

        $this->actingAs($admin)->post("/admin/orders/{$order->order_number}/unavailable", ['note' => 'Out of stock']);

        Mail::assertQueued(OrderUnavailableMail::class, fn ($mail) => $mail->hasTo('zaid@example.com'));
    }

    /**
     * ⚠️ The note staff type is APPENDED TO `admin_notes` — the same field they
     * keep their own running commentary in. It must never reach the customer.
     */
    public function test_the_unavailable_email_never_leaks_internal_staff_notes(): void
    {
        $order = $this->makeOrder();
        $order->forceFill(['admin_notes' => 'Customer is difficult, low priority'])->save();

        $html = (new OrderUnavailableMail($order->refresh()))->render();

        $this->assertStringNotContainsString('Customer is difficult', $html);
    }

    /** The refund sentence has to match how they actually paid. */
    public function test_the_unavailable_email_explains_the_right_refund_route(): void
    {
        $card = $this->makeOrder(['payment_method' => PaymentMethod::Card]);
        $this->assertStringContainsString(
            __('emails.unavailable.refund_card', [], 'ar'),
            (new OrderUnavailableMail($card))->render(),
        );

        $transfer = $this->makeOrder(['payment_method' => PaymentMethod::BankTransfer]);
        $this->assertStringContainsString(
            __('emails.unavailable.refund_transfer', [], 'ar'),
            (new OrderUnavailableMail($transfer))->render(),
        );
    }

    /**
     * 🔑 Delivery opens the 3-day return window, and it runs from `delivered_at`
     * whether or not the customer was told. This email is what turns the policy
     * from a trap into a promise — and it must quote the SAME number the
     * eligibility check will apply.
     */
    public function test_the_delivered_email_states_the_real_return_window(): void
    {
        $order = $this->makeOrder(['status' => OrderStatus::Shipped]);

        $html = (new OrderDeliveredMail($order))->render();

        $this->assertStringContainsString(
            __('emails.delivered.returns_intro', ['days' => ReturnService::WINDOW_DAYS], 'ar'),
            $html,
        );
    }

    /**
     * 🔑 Driven through the real status-update path the OTO webhook calls, not by
     * rendering the mailable: the point is that DELIVERY reaches the mailer.
     * Nothing else in the system told the customer their order had arrived.
     */
    public function test_delivery_emails_the_customer(): void
    {
        Mail::fake();
        $order = $this->makeOrder(['status' => OrderStatus::Shipped]);

        app(ShippingService::class)->applyStatusUpdate($order->order_number, 'delivered');

        $this->assertNotNull($order->refresh()->delivered_at, 'delivery must stamp the return window');
        Mail::assertQueued(OrderDeliveredMail::class, fn ($mail) => $mail->hasTo('zaid@example.com'));
    }

    /**
     * All four return touchpoints were WhatsApp-only: a customer could send
     * photos of a damaged order and hear nothing through the whole process.
     */
    public function test_a_return_update_emails_the_customer(): void
    {
        Mail::fake();
        $order = $this->makeOrder(['status' => OrderStatus::Delivered]);
        $order->forceFill(['delivered_at' => now()])->save();

        $return = OrderReturn::create([
            'order_id' => $order->id,
            'user_id' => null,
            'status' => ReturnStatus::Requested,
            'reason' => 'Damaged on arrival',
        ]);

        // 🔴 Driven through the REAL ReturnService, not by calling the mailer
        // directly. The thing under test is that the return flow reaches the
        // mailer at all — a test that invokes CustomerMailer itself would pass
        // just as happily with the call site deleted, which is exactly the
        // failure being guarded against.
        app(ReturnService::class)->approve($return, null);

        Mail::assertQueued(ReturnUpdateMail::class, fn ($mail) => $mail->hasTo('zaid@example.com'));
    }

    /**
     * 🔑 The subject names the STATUS, not the items. Several of these arrive for
     * one order as the request moves through its states, and four identical
     * "…: سكري" subjects would be unreadable — Gmail would fold them into one.
     */
    public function test_return_update_subjects_differ_by_status(): void
    {
        $order = $this->makeOrder();
        $subjects = [];

        foreach ([ReturnStatus::Requested, ReturnStatus::Approved, ReturnStatus::Refunded] as $status) {
            $return = OrderReturn::create([
                'order_id' => $order->id,
                'status' => $status,
                'reason' => 'Damaged',
            ]);
            $subjects[] = (new ReturnUpdateMail($return))->envelope()->subject;
        }

        $this->assertCount(3, array_unique($subjects), 'each return state needs its own subject');
        foreach ($subjects as $subject) {
            $this->assertStringContainsString($order->order_number, $subject);
        }
    }

    // ---------------------------------------------------------------------
    // Email is mandatory while it is the only channel that reaches anyone.
    // ---------------------------------------------------------------------

    /**
     * 🔴 Without an address the customer gets NOTHING — no receipt, no
     * confirmation, no tracking, and no word when the order cannot be filled.
     * Refusing the order is better than taking money we cannot acknowledge.
     */
    public function test_checkout_requires_an_email_while_it_is_the_only_channel(): void
    {
        $this->seedCart();

        $this->post('/checkout', [
            'customer_name' => 'Zaid',
            'customer_phone' => '+966500000000',
            'country' => 'SA',
            'city' => 'Riyadh',
            'payment_method' => 'bank_transfer',
        ])->assertSessionHasErrors('customer_email');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * ⚠️ And it stays a STOPGAP: one env var restores phone-only checkout once
     * WhatsApp can carry these messages. A flag nobody can turn off is just a
     * hardcoded rule with extra steps.
     */
    public function test_phone_only_checkout_returns_when_the_flag_is_off(): void
    {
        config(['retab.require_customer_email' => false]);
        $this->seedCart();

        $this->post('/checkout', [
            'customer_name' => 'Zaid',
            'customer_phone' => '+966500000000',
            'country' => 'SA',
            'city' => 'Riyadh',
            'payment_method' => 'bank_transfer',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 1);
    }
}
