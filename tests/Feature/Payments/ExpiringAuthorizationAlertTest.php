<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentExpiringNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The alert for a Tamara authorisation about to lapse.
 *
 * 🔴 The failure it prevents is silent: Tamara holds the funds and we capture at
 * admin confirmation, so a window that closes unnoticed means the order can
 * never be captured. No error, no failed job, just a sale that stops being
 * collectable.
 */
class ExpiringAuthorizationAlertTest extends TestCase
{
    use RefreshDatabase;

    private function heldOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'RTB-EXP-'.fake()->unique()->numberBetween(1, 99999),
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Authorized,
            'payment_method' => PaymentMethod::Tamara,
            'payment_gateway' => 'tamara',
            'gateway_reference' => 'tamara-'.fake()->uuid(),
            'subtotal' => 100,
            'total' => 125,
        ], $overrides));
    }

    /** Writes the authorization ledger row that dates the hold. */
    private function authorize(Order $order, int $hoursAgo): void
    {
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'tamara',
            'gateway_transaction_id' => 'auth-'.$order->id,
            'type' => PaymentTransactionType::Authorization,
            'amount' => (float) $order->total,
            'currency' => 'SAR',
            'status' => 'authorized',
        ])->forceFill(['created_at' => now()->subHours($hoursAgo)])->save();
    }

    public function test_an_authorisation_inside_its_warning_window_alerts_staff(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->heldOrder();
        $this->authorize($order, 40); // 48h window, warn at 36h

        $this->artisan('payments:alert-expiring')->assertSuccessful();

        Notification::assertSentTo($admin, PaymentExpiringNotification::class);
        $this->assertNotNull($order->fresh()->payment_expiry_alerted_at);
    }

    public function test_a_fresh_authorisation_is_left_alone(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);
        $order = $this->heldOrder();
        $this->authorize($order, 2);

        $this->artisan('payments:alert-expiring')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($order->fresh()->payment_expiry_alerted_at);
    }

    /**
     * 🔑 The stamp is the point. An hourly command with no memory would re-alert
     * every run for the whole window, which trains staff to ignore the one
     * notification that costs real money.
     */
    public function test_an_order_is_only_alerted_once(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);
        $order = $this->heldOrder();
        $this->authorize($order, 40);

        $this->artisan('payments:alert-expiring');
        $this->artisan('payments:alert-expiring');

        Notification::assertSentTimes(PaymentExpiringNotification::class, 1);
    }

    /**
     * ⚠️ The hold is dated from the AUTHORIZATION ledger row, not from
     * `orders.created_at` (which is what the dashboard counter uses). An order
     * paid through the resume-payment route is authorised long after it was
     * placed, and dating it from creation would alert on a hold that has barely
     * started — or, on the other side of the same mistake, after it had lapsed.
     */
    public function test_the_hold_is_dated_from_the_authorisation_not_the_order(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);

        // Placed 3 days ago (abandoned), but only authorised an hour ago.
        $order = $this->heldOrder();
        $order->forceFill(['created_at' => now()->subDays(3)])->save();
        $this->authorize($order, 1);

        $this->artisan('payments:alert-expiring');

        Notification::assertNothingSent();
    }

    public function test_dry_run_reports_without_notifying_or_stamping(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);
        $order = $this->heldOrder();
        $this->authorize($order, 40);

        $this->artisan('payments:alert-expiring', ['--dry-run' => true])->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($order->fresh()->payment_expiry_alerted_at);
    }

    /** Only a held Tamara order awaiting confirmation is at risk. */
    public function test_orders_not_at_risk_are_ignored(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);

        $captured = $this->heldOrder(['payment_status' => PaymentStatus::Paid]);
        $confirmed = $this->heldOrder(['status' => OrderStatus::Confirmed]);
        $card = $this->heldOrder(['payment_method' => PaymentMethod::Card]);
        foreach ([$captured, $confirmed, $card] as $order) {
            $this->authorize($order, 40);
        }

        $this->artisan('payments:alert-expiring');

        Notification::assertNothingSent();
    }

    /** The schedule entry is what makes any of this run at all. */
    public function test_the_command_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command ?? '', 'payments:alert-expiring'));

        $this->assertCount(1, $events, 'payments:alert-expiring is not scheduled');
        $this->assertSame('0 * * * *', $events->first()->expression);
    }
}
