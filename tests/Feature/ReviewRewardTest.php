<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\SendReviewReminder;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReviewRewardService;
use App\Services\Shipping\ShippingGateway;
use App\Services\Shipping\ShippingService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReviewRewardTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'التمور', 'is_active' => true]);

        return Product::create([
            'category_id' => $cat->id, 'name_ar' => 'سكري', 'slug' => 'p-'.uniqid(),
            'price' => 50, 'sku' => 'SK-'.uniqid(), 'stock' => 10, 'is_active' => true,
        ]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'phone' => '+966500000000']);
    }

    private function orderFor(User $user, Product $product, OrderStatus $status = OrderStatus::Confirmed): Order
    {
        $order = Order::create([
            'user_id' => $user->id, 'order_number' => 'R-'.uniqid(), 'customer_name' => 'T',
            'customer_phone' => '+966500000000', 'shipping_address' => ['city' => 'Riyadh'],
            'status' => $status, 'subtotal' => 50, 'total' => 50,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_name_ar' => 'x',
            'unit_price' => 50, 'quantity' => 1, 'line_total' => 50,
        ]);

        return $order;
    }

    private function enable(int $percent = 20): void
    {
        Setting::set('review_reward_enabled', '1');
        Setting::set('review_reward_percent', (string) $percent);
    }

    // ---- Issuance ------------------------------------------------------------

    public function test_review_with_comment_issues_a_one_time_coupon_when_enabled(): void
    {
        $this->enable(20);
        $product = $this->product();
        $user = $this->customer();
        $this->orderFor($user, $product);

        $this->actingAs($user)
            ->post("/products/{$product->slug}/reviews", ['rating' => 5, 'body' => 'Great dates!'])
            ->assertRedirect();

        $coupon = Coupon::where('user_id', $user->id)->where('source', 'review')->first();
        $this->assertNotNull($coupon);
        $this->assertSame('20.00', (string) $coupon->value);
        $this->assertSame(1, $coupon->usage_limit);
        $this->assertTrue($coupon->expires_at->between(now()->addDays(29), now()->addDays(31)));
        $this->assertDatabaseHas('loyalty_rewards', ['user_id' => $user->id, 'type' => 'review']);
    }

    public function test_no_coupon_when_the_feature_is_off(): void
    {
        $product = $this->product(); // disabled by default
        $user = $this->customer();
        $this->orderFor($user, $product);

        $this->actingAs($user)->post("/products/{$product->slug}/reviews", ['rating' => 5, 'body' => 'Nice']);

        $this->assertSame(0, Coupon::where('source', 'review')->count());
    }

    public function test_a_rating_only_review_earns_nothing(): void
    {
        $this->enable();
        $product = $this->product();
        $user = $this->customer();
        $this->orderFor($user, $product);

        $this->actingAs($user)->post("/products/{$product->slug}/reviews", ['rating' => 5]); // no comment

        $this->assertSame(0, Coupon::where('source', 'review')->count());
    }

    public function test_the_reward_is_issued_only_once_per_customer(): void
    {
        $this->enable();
        $user = $this->customer();
        $p1 = $this->product();
        $p2 = $this->product();
        $this->orderFor($user, $p1);
        $this->orderFor($user, $p2);

        $this->actingAs($user)->post("/products/{$p1->slug}/reviews", ['rating' => 5, 'body' => 'a']);
        $this->actingAs($user)->post("/products/{$p2->slug}/reviews", ['rating' => 4, 'body' => 'b']);

        $this->assertSame(1, Coupon::where('user_id', $user->id)->where('source', 'review')->count());
    }

    // ---- Delivery → queued WhatsApp reminder ---------------------------------

    public function test_delivery_queues_the_reminder_when_enabled(): void
    {
        Queue::fake();
        $this->mock(ShippingGateway::class);
        $this->enable();
        $order = $this->orderFor($this->customer(), $this->product(), OrderStatus::Shipped);

        app(ShippingService::class)->applyStatusUpdate($order->order_number, 'delivered');

        Queue::assertPushed(SendReviewReminder::class);
    }

    public function test_delivery_does_not_queue_when_disabled(): void
    {
        Queue::fake();
        $this->mock(ShippingGateway::class);
        $order = $this->orderFor($this->customer(), $this->product(), OrderStatus::Shipped); // feature off

        app(ShippingService::class)->applyStatusUpdate($order->order_number, 'delivered');

        Queue::assertNotPushed(SendReviewReminder::class);
    }

    // ---- The reminder job ----------------------------------------------------

    public function test_reminder_job_sends_and_stamps_when_eligible(): void
    {
        $this->enable();
        $order = $this->orderFor($this->customer(), $this->product(), OrderStatus::Delivered);

        (new SendReviewReminder($order->id))->handle(app(WhatsAppService::class), app(ReviewRewardService::class));

        $this->assertNotNull($order->fresh()->review_reminder_sent_at);
        $this->assertDatabaseHas('whatsapp_messages', ['order_id' => $order->id, 'template' => 'review_reminder']);
    }

    public function test_reminder_job_skips_a_customer_who_already_claimed(): void
    {
        $this->enable();
        $user = $this->customer();
        $order = $this->orderFor($user, $this->product(), OrderStatus::Delivered);
        app(ReviewRewardService::class)->issueFor($user); // already earned it

        (new SendReviewReminder($order->id))->handle(app(WhatsAppService::class), app(ReviewRewardService::class));

        $this->assertNull($order->fresh()->review_reminder_sent_at);
        $this->assertDatabaseMissing('whatsapp_messages', ['order_id' => $order->id, 'template' => 'review_reminder']);
    }

    // ---- Admin control (on the Discounts page) -------------------------------

    public function test_review_reward_endpoint_saves_the_setting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/admin/discounts/review-reward', ['enabled' => true, 'percent' => 20])
            ->assertRedirect();

        $this->assertSame('1', Setting::get('review_reward_enabled'));
        $this->assertSame('20', Setting::get('review_reward_percent'));
    }

    public function test_review_reward_endpoint_rejects_a_bad_percent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from('/admin/discounts')
            ->post('/admin/discounts/review-reward', ['enabled' => true, 'percent' => 15])
            ->assertSessionHasErrors('percent');
    }
}
