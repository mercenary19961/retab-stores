<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\NormalizedPayment;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The recovery path for an abandoned card/Tamara checkout.
 *
 * Before this route existed the order sat in `pending_payment` with its
 * `payment_url` stored but never surfaced, so a customer who closed the gateway
 * tab could not pay and could not get back — a silently lost sale.
 */
class ResumePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'RTB-RESUME-1',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Card,
            'payment_gateway' => 'moyasar',
            'gateway_reference' => 'inv_1',
            'payment_url' => 'https://pay.test/inv_1',
            'subtotal' => 100,
            'total' => 125,
        ], $overrides));
    }

    /** Binds a gateway whose createInvoice always mints a NEW invoice id. */
    private function fakeGateway(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new class implements PaymentGateway
        {
            public function createInvoice(Order $order): array
            {
                return ['url' => 'https://pay.test/inv_FRESH', 'invoice_id' => 'inv_FRESH', 'raw' => []];
            }

            public function fetchPayment(string $paymentId): NormalizedPayment
            {
                return new NormalizedPayment($paymentId, 'paid', 12500, 'SAR', null, null, 'inv_1', null, null, []);
            }

            public function fetchInvoice(string $invoiceId): array
            {
                return ['status' => 'initiated', 'payments' => [], 'raw' => []];
            }

            public function verifyWebhookToken(?string $token): bool
            {
                return false;
            }

            public function refundPayment(string $paymentId, int $amount): NormalizedPayment
            {
                return new NormalizedPayment($paymentId, 'refunded', $amount, 'SAR', null, null, null, null, null, []);
            }

            public function toMinorUnits(float $amount): int
            {
                return (int) round($amount * 100);
            }
        });
    }

    public function test_a_guest_who_placed_the_order_can_resume_payment(): void
    {
        $order = $this->order();

        $response = $this->withSession(['placed_orders' => [$order->order_number]])
            ->post("/orders/{$order->order_number}/pay");

        // `Inertia::location` answers 409 + X-Inertia-Location to the Inertia client
        // and degrades to a plain 302 otherwise. Asserting the DESTINATION covers the
        // decision under test either way, without the version handshake.
        $response->assertRedirect('https://pay.test/inv_1');
    }

    public function test_the_signed_in_owner_can_resume_payment(): void
    {
        $user = User::forceCreate(['phone' => '+966500000001', 'role' => 'customer']);
        $order = $this->order(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/orders/{$order->order_number}/pay")
            ->assertRedirect('https://pay.test/inv_1');
    }

    /** 🔴 The one that matters for security: someone else's order is not payable. */
    public function test_a_stranger_cannot_resume_someone_elses_payment(): void
    {
        $order = $this->order();

        // No session entry, not signed in — i.e. only the order number is known.
        $this->post("/orders/{$order->order_number}/pay")->assertForbidden();
    }

    public function test_a_paid_order_cannot_be_paid_again(): void
    {
        $order = $this->order(['payment_status' => PaymentStatus::Paid, 'status' => OrderStatus::AwaitingConfirmation]);

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->post("/orders/{$order->order_number}/pay")
            ->assertForbidden();
    }

    /** Bank transfer has no gateway to return to; its instructions are on the page. */
    public function test_a_bank_transfer_order_is_not_resumable(): void
    {
        $order = $this->order(['payment_method' => PaymentMethod::BankTransfer, 'payment_url' => null]);

        $this->withSession(['placed_orders' => [$order->order_number]])
            ->post("/orders/{$order->order_number}/pay")
            ->assertForbidden();
    }

    /**
     * A FAILED attempt must not be handed back the same invoice — the gateway has
     * closed it, so the customer would land on a dead page. The stored session is
     * dropped so a fresh one is created.
     */
    public function test_a_failed_payment_starts_a_fresh_invoice(): void
    {
        $this->fakeGateway();
        $order = $this->order(['payment_status' => PaymentStatus::Failed]);

        $response = $this->withSession(['placed_orders' => [$order->order_number]])
            ->post("/orders/{$order->order_number}/pay");

        $response->assertRedirect('https://pay.test/inv_FRESH');
        $this->assertSame('inv_FRESH', $order->fresh()->gateway_reference);
    }

    /** A merely abandoned (never completed) attempt reuses its still-open invoice. */
    public function test_an_abandoned_payment_reuses_the_open_invoice(): void
    {
        $this->fakeGateway();
        $order = $this->order();

        $response = $this->withSession(['placed_orders' => [$order->order_number]])
            ->post("/orders/{$order->order_number}/pay");

        $response->assertRedirect('https://pay.test/inv_1');
        $this->assertSame('inv_1', $order->fresh()->gateway_reference);
    }

    /** The button only renders when the order is genuinely payable. */
    public function test_the_order_page_ships_can_pay(): void
    {
        $payable = $this->order();
        $this->withSession(['placed_orders' => [$payable->order_number]])
            ->get("/orders/{$payable->order_number}")
            ->assertInertia(fn ($page) => $page->where('canPay', true));

        $paid = $this->order([
            'order_number' => 'RTB-RESUME-2',
            'payment_status' => PaymentStatus::Paid,
            'status' => OrderStatus::AwaitingConfirmation,
        ]);
        $this->withSession(['placed_orders' => [$paid->order_number]])
            ->get("/orders/{$paid->order_number}")
            ->assertInertia(fn ($page) => $page->where('canPay', false));
    }

    /**
     * The chosen method is recorded at checkout for ALL three, not just bank
     * transfer. Without it an unpaid card order has a null `payment_method` and
     * nothing can tell which gateway to resume against.
     */
    public function test_checkout_records_the_chosen_payment_method_for_cards(): void
    {
        $this->fakeGateway();

        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'التمور', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'slug' => 'sukkari-resume',
            'price' => 50,
            'sku' => 'SK-RESUME',
            'stock' => 10,
            'is_active' => true,
        ]);
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/checkout', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000002',
            'country' => 'SA',
            'city' => 'Riyadh',
            'payment_method' => 'card',
        ]);

        $order = Order::latest('id')->first();
        $this->assertSame(PaymentMethod::Card, $order->payment_method);
    }
}
