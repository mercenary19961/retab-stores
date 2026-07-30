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
        return User::forceCreate(['name' => 'Admin', 'email' => $email, 'password' => bcrypt('secret'), 'role' => 'admin']);
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'RTB-'.uniqid(),
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
        $editor = User::forceCreate(['name' => 'Ed', 'email' => 'ed@test.com', 'password' => bcrypt('secret'), 'role' => 'editor']);
        $customer = User::forceCreate(['name' => 'Cust', 'email' => 'c@test.com', 'password' => bcrypt('secret')]);

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

    public function test_partial_reload_refreshes_only_the_notifications_prop(): void
    {
        // This is the mechanism behind the bell's live polling (router.poll with
        // `only: ['notifications']`): the shared closure must resolve on a partial
        // request, and the page's own heavier props must NOT be recomputed.
        $admin = $this->admin();
        $admin->notify(new NewOrderNotification($this->order()));

        // Matching asset version, or Inertia answers 409 (forced full reload).
        $version = $this->actingAs($admin)->get('/admin/dashboard')->inertiaPage()['version'];

        $props = $this->actingAs($admin)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) $version,
                'X-Inertia-Partial-Component' => 'admin/dashboard',
                'X-Inertia-Partial-Data' => 'notifications',
            ])
            ->getJson('/admin/dashboard')
            ->assertOk()
            ->json('props');

        $this->assertSame(1, $props['notifications']['unread']);
        $this->assertArrayNotHasKey('stats', $props);
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

    public function test_history_lists_only_your_own_notifications(): void
    {
        $owner = $this->admin('owner@test.com');
        $other = $this->admin('other@test.com');
        $owner->notify(new NewOrderNotification($this->order()));
        $other->notify(new NewOrderNotification($this->order()));

        $props = $this->actingAs($owner)->get('/admin/notifications')->assertOk()->inertiaPage()['props'];

        $this->assertCount(1, $props['entries']['data']);
        $this->assertSame(1, $props['entries']['total']);
    }

    public function test_history_does_not_shadow_the_shared_bell_prop(): void
    {
        // The page paginator is deliberately named `entries`: a page prop called
        // `notifications` would override the shared bell prop ({unread, items})
        // and break the bell on this very page.
        $admin = $this->admin();
        $admin->notify(new NewOrderNotification($this->order()));

        $props = $this->actingAs($admin)->get('/admin/notifications')->inertiaPage()['props'];

        $this->assertSame(1, $props['notifications']['unread']);
        $this->assertArrayHasKey('items', $props['notifications']);
        $this->assertArrayHasKey('data', $props['entries']);
    }

    public function test_history_filters_by_read_state_and_type(): void
    {
        $admin = $this->admin();
        $order = $this->order();
        $admin->notify(new NewOrderNotification($order));   // stays unread
        $admin->notify(new NewOrderNotification($order));
        $admin->notifications()->first()->markAsRead();

        $unread = $this->actingAs($admin)->get('/admin/notifications?status=unread')->inertiaPage()['props'];
        $this->assertSame(1, $unread['entries']['total']);
        $this->assertSame('unread', $unread['filters']['status']);

        $read = $this->actingAs($admin)->get('/admin/notifications?status=read')->inertiaPage()['props'];
        $this->assertSame(1, $read['entries']['total']);

        // JSON-path filter on the structured payload.
        $matching = $this->actingAs($admin)->get('/admin/notifications?type=new_order')->inertiaPage()['props'];
        $this->assertSame(2, $matching['entries']['total']);

        $otherType = $this->actingAs($admin)->get('/admin/notifications?type=return_requested')->inertiaPage()['props'];
        $this->assertSame(0, $otherType['entries']['total']);
    }

    public function test_history_ignores_a_bogus_type_filter(): void
    {
        $admin = $this->admin();
        $admin->notify(new NewOrderNotification($this->order()));

        // Not whitelisted → filter dropped entirely rather than reaching the query.
        $props = $this->actingAs($admin)->get('/admin/notifications?type=../../etc/passwd')->assertOk()->inertiaPage()['props'];

        $this->assertNull($props['filters']['type']);
        $this->assertSame(1, $props['entries']['total']);
    }

    public function test_history_paginates_with_the_whitelisted_page_size(): void
    {
        $admin = $this->admin();
        $order = $this->order();
        for ($i = 0; $i < 12; $i++) {
            $admin->notify(new NewOrderNotification($order));
        }

        $props = $this->actingAs($admin)->get('/admin/notifications?per_page=10')->inertiaPage()['props'];
        $this->assertCount(10, $props['entries']['data']);
        $this->assertSame(12, $props['entries']['total']);
        $this->assertSame(10, $props['filters']['per_page']);

        // Off-whitelist sizes fall back to the default instead of being honoured.
        $huge = $this->actingAs($admin)->get('/admin/notifications?per_page=5000')->inertiaPage()['props'];
        $this->assertSame(25, $huge['filters']['per_page']);
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
