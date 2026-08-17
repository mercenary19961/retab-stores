<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Two gaps on the customer account page:
 *  - unpaid orders were listed with no way to pay them, so an abandoned gateway
 *    checkout was only recoverable from a confirmation page the customer had
 *    already navigated away from;
 *  - an OTP-only customer could never set a password, because the shared
 *    `password.update` route demands a `current_password` they have never had.
 */
class AccountPaymentAndPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $user, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $user->id,
            'order_number' => 'RTB-ACC-'.fake()->unique()->numberBetween(1, 99999),
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Card,
            'subtotal' => 100,
            'total' => 125,
        ], $overrides));
    }

    public function test_the_account_list_flags_which_orders_can_still_be_paid(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->order($user);

        $this->actingAs($user)->get('/account')->assertInertia(
            fn (Assert $page) => $page->where('orders.0.can_pay', true),
        );
    }

    /** Bank transfer has no gateway to return to; its IBAN is on the order page. */
    public function test_a_bank_transfer_order_offers_no_pay_action(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->order($user, ['payment_method' => PaymentMethod::BankTransfer]);

        $this->actingAs($user)->get('/account')->assertInertia(
            fn (Assert $page) => $page->where('orders.0.can_pay', false),
        );
    }

    public function test_a_settled_order_offers_no_pay_action(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->order($user, [
            'payment_status' => PaymentStatus::Paid,
            'status' => OrderStatus::AwaitingConfirmation,
        ]);

        $this->actingAs($user)->get('/account')->assertInertia(
            fn (Assert $page) => $page->where('orders.0.can_pay', false),
        );
    }

    /**
     * 🔑 The account list, the order page and the pay route must agree, so the
     * rule lives on the model and every surface asks it.
     */
    public function test_the_pay_flag_matches_the_route_that_enforces_it(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $payable = $this->order($user);
        $settled = $this->order($user, ['payment_status' => PaymentStatus::Paid, 'status' => OrderStatus::AwaitingConfirmation]);

        $this->assertTrue($payable->isAwaitingGatewayPayment());
        $this->assertFalse($settled->isAwaitingGatewayPayment());

        // The route refuses exactly what the flag says it will.
        $this->actingAs($user)->post("/orders/{$settled->order_number}/pay")->assertForbidden();
    }

    public function test_an_otp_only_customer_sets_a_first_password(): void
    {
        $user = User::forceCreate([
            'phone' => '+966500000001',
            'email' => 'otp@retab.test',
            'role' => 'customer',
            'password' => null,
        ]);

        $this->actingAs($user)
            ->post('/account/password', ['password' => 'sup3r-secret-pw', 'password_confirmation' => 'sup3r-secret-pw'])
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('sup3r-secret-pw', $user->fresh()->password));
    }

    /**
     * 🔴 The guard that IS the security model: allowing this on an account that
     * already has a password would turn a hijacked session into a permanent
     * takeover with no knowledge of the old credential.
     */
    public function test_an_account_that_already_has_a_password_is_refused(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'existing-password']);
        $before = $user->password;

        $this->actingAs($user)
            ->post('/account/password', ['password' => 'attacker-chosen-pw', 'password_confirmation' => 'attacker-chosen-pw'])
            ->assertForbidden();

        $this->assertSame($before, $user->fresh()->password);
    }

    /** A password with no email to pair it with cannot be used to sign in. */
    public function test_a_password_is_refused_without_an_email_to_sign_in_with(): void
    {
        $user = User::forceCreate(['phone' => '+966500000002', 'role' => 'customer', 'password' => null]);

        $this->actingAs($user)
            ->post('/account/password', ['password' => 'sup3r-secret-pw', 'password_confirmation' => 'sup3r-secret-pw'])
            ->assertSessionHas('error', __('messages.profile.email_needed_first'));

        $this->assertNull($user->fresh()->password);
    }

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $user = User::forceCreate(['phone' => '+966500000003', 'email' => 'otp2@retab.test', 'role' => 'customer', 'password' => null]);

        $this->actingAs($user)
            ->post('/account/password', ['password' => 'sup3r-secret-pw', 'password_confirmation' => 'different-pw'])
            ->assertSessionHasErrors('password');

        $this->assertNull($user->fresh()->password);
    }

    /** The profile page tells the UI which of the two states it is in. */
    public function test_the_profile_page_ships_the_password_flags(): void
    {
        $otp = User::forceCreate(['phone' => '+966500000004', 'email' => 'otp3@retab.test', 'role' => 'customer', 'password' => null]);

        $this->actingAs($otp)->get('/account/profile')->assertInertia(
            fn (Assert $page) => $page
                ->where('profile.has_password', false)
                ->where('profile.can_set_password', true),
        );

        $noEmail = User::forceCreate(['phone' => '+966500000005', 'role' => 'customer', 'password' => null]);

        $this->actingAs($noEmail)->get('/account/profile')->assertInertia(
            fn (Assert $page) => $page
                ->where('profile.has_password', false)
                ->where('profile.can_set_password', false),
        );
    }
}
