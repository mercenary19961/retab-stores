<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyServiceTest extends TestCase
{
    use RefreshDatabase;

    private function orderFor(?int $userId): Order
    {
        return Order::create([
            'order_number' => 'RTB-' . uniqid(),
            'user_id' => $userId,
            'customer_name' => 'Zaid',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'subtotal' => 100,
            'total' => 125,
        ]);
    }

    public function test_fifth_confirmed_purchase_issues_a_15_percent_user_bound_coupon(): void
    {
        $user = User::factory()->create();
        $service = app(LoyaltyService::class);

        $reward = null;
        for ($i = 0; $i < 5; $i++) {
            $reward = $service->recordConfirmedPurchase($this->orderFor($user->id));
        }

        $this->assertSame(5, $user->fresh()->confirmed_purchases_count);
        $this->assertNotNull($reward);
        $this->assertDatabaseHas('loyalty_rewards', ['user_id' => $user->id, 'threshold' => 5]);

        $coupon = Coupon::find($reward->coupon_id);
        $this->assertSame('loyalty', $coupon->source);
        $this->assertEquals(15, (float) $coupon->value);
        $this->assertSame($user->id, $coupon->user_id);
    }

    public function test_fewer_than_five_purchases_issues_nothing(): void
    {
        $user = User::factory()->create();
        $service = app(LoyaltyService::class);

        for ($i = 0; $i < 4; $i++) {
            $service->recordConfirmedPurchase($this->orderFor($user->id));
        }

        $this->assertSame(4, $user->fresh()->confirmed_purchases_count);
        $this->assertDatabaseCount('loyalty_rewards', 0);
    }

    public function test_guest_orders_do_not_accrue_loyalty(): void
    {
        $reward = app(LoyaltyService::class)->recordConfirmedPurchase($this->orderFor(null));

        $this->assertNull($reward);
        $this->assertDatabaseCount('loyalty_rewards', 0);
    }
}
