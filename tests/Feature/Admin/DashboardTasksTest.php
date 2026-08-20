<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Support\ExpiringAuthorizations;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The dashboard's "Needs attention" action queue.
 *
 * Covers the three things that were wrong with it: it offered work the viewer
 * had no permission to do, its Tamara counter disagreed with the alert that
 * acts on the same orders, and the never-shrinking draft backlog sat among
 * genuine same-day decisions.
 */
class DashboardTasksTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'a'.uniqid().'@test.com',
            'password' => bcrypt('x'), 'role' => 'admin',
        ]);
    }

    private function editor(array $permissions): User
    {
        return User::forceCreate([
            'name' => 'Editor', 'email' => 'e'.uniqid().'@test.com',
            'password' => bcrypt('x'), 'role' => 'editor', 'permissions' => $permissions,
        ]);
    }

    /** @return list<string> */
    private function taskKeys(User $viewer): array
    {
        $keys = [];
        $this->actingAs($viewer)->get('/admin/dashboard')->assertOk()
            ->assertInertia(function (Assert $page) use (&$keys) {
                $keys = array_column($page->toArray()['props']['tasks'], 'key');
            });

        return $keys;
    }

    private function tamaraOrder(?\DateTimeInterface $authorizedAt = null): Order
    {
        $order = Order::create([
            'order_number' => 'RTB-'.uniqid(),
            'customer_name' => 'عميل',
            'customer_phone' => '+966500000000',
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Authorized,
            'payment_method' => PaymentMethod::Tamara,
            'subtotal' => 100, 'shipping_fee' => 25, 'total' => 125,
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'locale' => 'ar',
        ]);

        if ($authorizedAt) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'gateway' => 'tamara',
                'type' => PaymentTransactionType::Authorization->value,
                'status' => 'authorized',
                'amount' => 125,
                'currency' => 'SAR',
            ]);
            // created_at is not fillable; set it directly so the hold can be aged.
            $payment->forceFill(['created_at' => $authorizedAt])->save();
        }

        return $order;
    }

    // ---------------------------------------------------------------- permissions

    public function test_an_admin_sees_every_tile(): void
    {
        $this->assertSame(
            ['awaitingConfirmation', 'bankTransfers', 'returnsToReview', 'readyToShip', 'tamaraExpiring'],
            $this->taskKeys($this->admin()),
        );
    }

    public function test_a_catalogue_editor_is_offered_no_order_or_return_tiles(): void
    {
        // Previously this editor received all six and four of them 403'd on click.
        $keys = $this->taskKeys($this->editor(Permission::preset('catalogue')));

        $this->assertSame([], $keys, 'a catalogue-only editor should be offered nothing here');
    }

    public function test_an_operations_editor_sees_orders_and_returns(): void
    {
        $keys = $this->taskKeys($this->editor(Permission::preset('operations')));

        $this->assertContains('awaitingConfirmation', $keys);
        $this->assertContains('returnsToReview', $keys);
    }

    public function test_every_offered_tile_leads_somewhere_the_viewer_can_actually_open(): void
    {
        // 🔑 The real invariant, rather than a hardcoded key list: whatever the
        // dashboard offers must not 403. This keeps holding when a tile is added.
        foreach ([Permission::preset('operations'), Permission::preset('catalogue'), Permission::preset('manager')] as $perms) {
            $editor = $this->editor($perms);
            $tasks = [];
            $this->actingAs($editor)->get('/admin/dashboard')
                ->assertInertia(function (Assert $page) use (&$tasks) {
                    $tasks = $page->toArray()['props']['tasks'];
                });

            foreach ($tasks as $task) {
                // assertSuccessful() takes no message and PHP silently drops extra
                // arguments, so assert the status directly — the explanation is the
                // whole point of this test when it fails.
                $status = $this->actingAs($editor)->get($task['href'])->getStatusCode();
                $this->assertLessThan(
                    400,
                    $status,
                    "dashboard offered '{$task['key']}' but {$task['href']} returned {$status}"
                );
            }
        }
    }

    // ------------------------------------------------------------ tamara counting

    public function test_the_tamara_tile_counts_the_same_orders_the_alert_acts_on(): void
    {
        // Authorised 40h ago: past the 36h alert threshold (48 - 12).
        $this->tamaraOrder(now()->subHours(40));
        // Authorised 2h ago: nowhere near expiry.
        $this->tamaraOrder(now()->subHours(2));

        $count = null;
        $this->actingAs($this->admin())->get('/admin/dashboard')
            ->assertInertia(function (Assert $page) use (&$count) {
                foreach ($page->toArray()['props']['tasks'] as $t) {
                    if ($t['key'] === 'tamaraExpiring') {
                        $count = $t['count'];
                    }
                }
            });

        $this->assertSame(1, $count);
        // The dashboard and the command must agree, by construction.
        $this->assertSame(ExpiringAuthorizations::approaching()->count(), $count);
    }

    public function test_the_hold_is_dated_from_the_authorization_row_not_the_order(): void
    {
        // 🔴 The old bug: an order PLACED 40h ago but only authorised 2h ago (the
        // resume-payment route) was counted as expiring when its hold had barely
        // started. Dating it from created_at is what made that happen.
        $order = $this->tamaraOrder(now()->subHours(2));
        $order->forceFill(['created_at' => now()->subHours(40)])->save();

        $this->assertSame(0, ExpiringAuthorizations::approaching()->count());
    }

    public function test_an_order_already_alerted_still_shows_on_the_dashboard(): void
    {
        // The stamp stops the hourly command re-notifying; it must not hide an
        // order that is still sitting there unconfirmed.
        $order = $this->tamaraOrder(now()->subHours(40));
        $order->forceFill(['payment_expiry_alerted_at' => now()])->save();

        $this->assertSame(1, ExpiringAuthorizations::approaching()->count());
    }

    // -------------------------------------------------------------- recent orders

    public function test_recent_orders_are_withheld_from_a_viewer_without_orders_access(): void
    {
        $this->tamaraOrder();

        // 🔑 null, not [] — every row links to /admin/orders/{n}, so an empty list
        // would render "no orders yet", which is a different (and false) claim from
        // "not shown to you". The panel keys off null to hide itself entirely.
        $this->actingAs($this->editor(Permission::preset('catalogue')))
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('recentOrders', null));

        $this->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page->has('recentOrders', 1));
    }

    // ---------------------------------------------------------------------- money

    public function test_revenue_is_withheld_from_editors(): void
    {
        $this->actingAs($this->editor(Permission::preset('manager')))
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                // null, not zeroes: "0 SAR" would be a convincing lie rather than
                // a withheld figure, and the page hides the block on null.
                ->where('kpis', null)
                ->where('trend', null)
            );
    }

    public function test_an_admin_still_sees_revenue(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page->has('kpis.revenue30')->has('trend'));
    }

    public function test_top_products_keep_the_ranking_but_drop_the_takings(): void
    {
        // What sells is operational and useful to whoever runs the catalogue;
        // what it earned is not.
        $manager = $this->editor(Permission::preset('manager'));

        $this->actingAs($manager)->get('/admin/dashboard')
            ->assertInertia(function (Assert $page) {
                foreach ($page->toArray()['props']['insights']['topProducts'] as $row) {
                    $this->assertNull($row['revenue'], 'revenue leaked to a non-admin');
                    $this->assertArrayHasKey('qty', $row);
                }
            });
    }

    // --------------------------------------------------------------------- drafts

    public function test_drafts_are_reported_as_inventory_not_as_a_task(): void
    {
        Product::create([
            'category_id' => Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'التمور', 'is_active' => true])->id,
            'name_ar' => 'مسودة', 'slug' => 'draft-'.uniqid(), 'sku' => 'D-'.uniqid(),
            'price' => 10, 'stock' => 0, 'is_active' => false,
        ]);

        $this->actingAs($this->admin())->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('inventory.drafts', 1)
                // A backlog that never reaches zero does not belong in a queue of
                // decisions due today.
                ->where('tasks', fn ($tasks) => ! in_array('draftsToComplete', array_column($tasks->toArray(), 'key'), true))
            );
    }
}
