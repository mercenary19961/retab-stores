<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'admin@test.com'): User
    {
        return User::create(['name' => 'Admin', 'email' => $email, 'password' => bcrypt('secret'), 'role' => 'admin']);
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'RTB-' . uniqid(),
            'customer_name' => 'زيد',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA'],
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'subtotal' => 100,
            'total' => 100,
        ]);
    }

    public function test_new_order_notifies_all_staff_but_not_customers(): void
    {
        $admin = $this->admin();
        $editor = User::create(['name' => 'Ed', 'email' => 'ed@test.com', 'password' => bcrypt('secret'), 'role' => 'editor']);
        $customer = User::create(['name' => 'Cust', 'email' => 'c@test.com', 'password' => bcrypt('secret')]);

        $order = $this->order();
        Notification::send(User::staff()->get(), new NewOrderNotification($order));

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $editor->notifications()->count());
        $this->assertSame(0, $customer->notifications()->count());

        $data = $admin->notifications()->first()->data;
        $this->assertSame('new_order', $data['type']);
        $this->assertSame($order->order_number, $data['order_number']);
        $this->assertSame("/admin/orders/{$order->order_number}", $data['url']);
    }

    public function test_shared_prop_exposes_unread_count_and_items(): void
    {
        $admin = $this->admin();
        $admin->notify(new NewOrderNotification($this->order()));

        $props = $this->actingAs($admin)->get('/admin/dashboard')->inertiaPage()['props'];

        $this->assertSame(1, $props['notifications']['unread']);
        $this->assertCount(1, $props['notifications']['items']);
        $this->assertFalse($props['notifications']['items'][0]['read']);
    }

    public function test_open_marks_read_and_redirects_to_target(): void
    {
        $admin = $this->admin();
        $order = $this->order();
        $admin->notify(new NewOrderNotification($order));
        $note = $admin->notifications()->first();

        $this->actingAs($admin)
            ->get("/admin/notifications/{$note->id}")
            ->assertRedirect("/admin/orders/{$order->order_number}");

        $this->assertNotNull($admin->fresh()->notifications()->first()->read_at);
    }

    public function test_cannot_open_another_users_notification(): void
    {
        $owner = $this->admin('owner@test.com');
        $other = $this->admin('other@test.com');
        $owner->notify(new NewOrderNotification($this->order()));
        $note = $owner->notifications()->first();

        $this->actingAs($other)->get("/admin/notifications/{$note->id}")->assertNotFound();
    }

    public function test_read_all_clears_unread(): void
    {
        $admin = $this->admin();
        $order = $this->order();
        $admin->notify(new NewOrderNotification($order));
        $admin->notify(new NewOrderNotification($order));

        $this->assertSame(2, $admin->unreadNotifications()->count());

        $this->actingAs($admin)->post('/admin/notifications/read-all')->assertRedirect();

        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());
    }
}
