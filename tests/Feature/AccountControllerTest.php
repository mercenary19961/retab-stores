<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_dashboard_shows_orders_and_loyalty_progress(): void
    {
        $user = User::factory()->create(['confirmed_purchases_count' => 3]);

        Order::create([
            'order_number' => 'RTB-1',
            'user_id' => $user->id,
            'customer_name' => 'Zaid',
            'customer_phone' => '966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 100,
            'total' => 125,
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/dashboard')
                ->has('orders', 1)
                ->where('loyalty.confirmed_purchases', 3)
                ->where('loyalty.remaining', 2)); // 3 of 5 → 2 to go
    }

    public function test_profile_update_changes_fields_and_stamps_opt_in(): void
    {
        $user = User::factory()->create(['whatsapp_opt_in' => false, 'whatsapp_opt_in_at' => null]);

        $this->actingAs($user)
            ->patch('/account/profile', [
                'name' => 'Zaid Sabbagh',
                'email' => 'zaid@example.com',
                'city' => 'Riyadh',
                'whatsapp_opt_in' => true,
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('Zaid Sabbagh', $user->name);
        $this->assertSame('Riyadh', $user->city);
        $this->assertTrue($user->whatsapp_opt_in);
        $this->assertNotNull($user->whatsapp_opt_in_at); // consent moment captured
    }
}
