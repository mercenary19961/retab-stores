<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ReviewService
    {
        return app(ReviewService::class);
    }

    private function product(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name_ar' => 'سكري',
            'slug' => 'p-' . uniqid(),
            'price' => 50,
            'sku' => 'SK-' . uniqid(),
            'stock' => 10,
        ]);
    }

    private function orderFor(User $user, Product $product, OrderStatus $status): Order
    {
        $order = Order::create([
            'order_number' => 'RTB-' . uniqid(),
            'user_id' => $user->id,
            'customer_name' => 'Zaid',
            'customer_phone' => '966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 50,
            'total' => 75,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name_ar' => $product->name_ar,
            'unit_price' => 50,
            'quantity' => 1,
            'line_total' => 50,
        ]);

        return $order;
    }

    public function test_only_a_purchaser_is_eligible(): void
    {
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();
        $product = $this->product();
        $this->orderFor($buyer, $product, OrderStatus::Delivered);

        $this->assertNotNull($this->service()->eligibleOrderId($buyer, $product->id));
        $this->assertNull($this->service()->eligibleOrderId($stranger, $product->id));
    }

    public function test_pending_order_does_not_make_eligible(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $this->orderFor($user, $product, OrderStatus::PendingPayment);

        $this->assertNull($this->service()->eligibleOrderId($user, $product->id));
    }

    public function test_submit_creates_an_approved_verified_review(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $order = $this->orderFor($user, $product, OrderStatus::Confirmed);

        $review = $this->service()->submit($user, $product, 5, 'ممتاز', 'تمر رائع');

        $this->assertTrue($review->is_approved);
        $this->assertSame($order->id, $review->order_id);
        $this->assertSame(5, $review->rating);
    }

    public function test_submit_rejects_a_non_purchaser(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $this->expectException(\RuntimeException::class);
        $this->service()->submit($user, $product, 4, null, null);
    }

    public function test_resubmitting_updates_the_same_review(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $this->orderFor($user, $product, OrderStatus::Delivered);

        $this->service()->submit($user, $product, 3, null, 'ok');
        $this->service()->submit($user, $product, 5, null, 'much better');

        $this->assertSame(1, Review::where('user_id', $user->id)->where('product_id', $product->id)->count());
        $this->assertSame(5, Review::first()->rating);
    }

    public function test_toggle_helpful_updates_count_and_blocks_self_vote(): void
    {
        $author = User::factory()->create();
        $voter = User::factory()->create();
        $product = $this->product();
        $this->orderFor($author, $product, OrderStatus::Delivered);
        $review = $this->service()->submit($author, $product, 4, null, null);

        $this->assertTrue($this->service()->toggleHelpful($voter, $review));
        $this->assertSame(1, $review->fresh()->helpful_count);

        $this->assertFalse($this->service()->toggleHelpful($voter, $review)); // toggle off
        $this->assertSame(0, $review->fresh()->helpful_count);

        $this->expectException(\RuntimeException::class);
        $this->service()->toggleHelpful($author, $review);
    }
}
