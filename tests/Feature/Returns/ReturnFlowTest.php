<?php

namespace Tests\Feature\Returns;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\NormalizedPayment;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::create([
            'name' => 'Customer',
            'email' => 'customer@test.com',
            'password' => bcrypt('secret'),
        ]);
    }

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    /** @return array{0: Order, 1: OrderItem} */
    private function makeDeliveredOrder(User $user, array $overrides = []): array
    {
        $order = Order::create(array_merge([
            'order_number' => 'RTB-' . fake()->unique()->numerify('######'),
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => 'card',
            'payment_gateway' => 'moyasar',
            'subtotal' => 100,
            'shipping_fee' => 25,
            'total' => 125,
            'delivered_at' => now()->subDay(),
        ], $overrides));

        $item = $order->items()->create([
            'product_name_ar' => 'تمر سكري',
            'product_name_en' => 'Sukkari Dates',
            'quantity' => 2,
            'unit_price' => 50,
            'line_total' => 100,
        ]);

        return [$order, $item];
    }

    private function fileReturn(User $user, Order $order, OrderItem $item): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post("/orders/{$order->order_number}/return", [
            'reason' => 'وصلت التمور تالفة',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
            'photos' => [UploadedFile::fake()->image('damage.jpg')],
        ]);
    }

    public function test_customer_files_a_return_with_photos(): void
    {
        Storage::fake('public');
        $user = $this->makeCustomer();
        [$order, $item] = $this->makeDeliveredOrder($user);

        $response = $this->fileReturn($user, $order, $item);

        $response->assertRedirect("/orders/{$order->order_number}");
        $response->assertSessionHas('success');

        $return = OrderReturn::firstOrFail();
        $this->assertSame(ReturnStatus::Requested, $return->status);
        $this->assertCount(1, $return->photos);
        Storage::disk('public')->assertExists($return->photos[0]);
        $this->assertDatabaseHas('return_items', [
            'order_return_id' => $return->id,
            'order_item_id' => $item->id,
            'quantity' => 1,
        ]);
    }

    public function test_return_blocked_outside_the_window(): void
    {
        Storage::fake('public');
        $user = $this->makeCustomer();
        [$order, $item] = $this->makeDeliveredOrder($user, ['delivered_at' => now()->subDays(4)]);

        $this->actingAs($user)->get("/orders/{$order->order_number}/return")->assertNotFound();

        $response = $this->fileReturn($user, $order, $item);
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('order_returns', 0);
    }

    public function test_second_return_for_the_same_order_is_blocked(): void
    {
        Storage::fake('public');
        $user = $this->makeCustomer();
        [$order, $item] = $this->makeDeliveredOrder($user);

        $this->fileReturn($user, $order, $item)->assertSessionHas('success');
        $this->fileReturn($user, $order, $item)->assertSessionHas('error');
        $this->assertDatabaseCount('order_returns', 1);
    }

    public function test_non_owner_cannot_open_the_return_form(): void
    {
        $owner = $this->makeCustomer();
        [$order] = $this->makeDeliveredOrder($owner);

        $other = User::create(['name' => 'Other', 'email' => 'other@test.com', 'password' => bcrypt('secret')]);

        $this->actingAs($other)->get("/orders/{$order->order_number}/return")->assertForbidden();
    }

    public function test_admin_refund_resolves_the_return_through_the_gateway(): void
    {
        Storage::fake('public');
        $user = $this->makeCustomer();
        [$order, $item] = $this->makeDeliveredOrder($user);
        $this->fileReturn($user, $order, $item);

        // The card capture the refund is issued against.
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'moyasar',
            'gateway_transaction_id' => 'pay_1',
            'type' => 'capture',
            'amount' => 125,
            'currency' => 'SAR',
            'status' => 'succeeded',
        ]);

        $this->app->instance(PaymentGateway::class, new class implements PaymentGateway
        {
            public array $refunds = [];

            public function createInvoice(Order $order): array
            {
                return ['url' => '', 'invoice_id' => '', 'raw' => []];
            }

            public function fetchPayment(string $paymentId): NormalizedPayment
            {
                throw new \RuntimeException('not used');
            }

            public function fetchInvoice(string $invoiceId): array
            {
                return ['status' => 'paid', 'payments' => [], 'raw' => []];
            }

            public function verifyWebhookToken(?string $token): bool
            {
                return false;
            }

            public function refundPayment(string $paymentId, int $amount): NormalizedPayment
            {
                $this->refunds[] = [$paymentId, $amount];

                return new NormalizedPayment(id: $paymentId, status: 'refunded', amount: $amount, currency: 'SAR');
            }
        });

        $admin = $this->makeAdmin();
        $return = OrderReturn::firstOrFail();

        $this->actingAs($admin)->post("/admin/returns/{$return->id}/approve")->assertSessionHas('success');

        // Refund items only (1 × 50 SAR), shipping kept → partial refund.
        $this->actingAs($admin)
            ->post("/admin/returns/{$return->id}/refund", ['refund_shipping' => false])
            ->assertSessionHas('success');

        $return->refresh();
        $this->assertSame(ReturnStatus::Refunded, $return->status);
        $this->assertSame(50.0, (float) $return->refund_amount);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'type' => 'refund',
            'status' => 'succeeded',
        ]);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->fresh()->payment_status);
    }
}
