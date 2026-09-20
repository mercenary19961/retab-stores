<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\ShippingCarrier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order page's day-to-day tools: recording a bank transfer, staff notes, the
 * packing slip, and which actions a view-only editor is (not) offered.
 */
class OrderOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function viewOnlyEditor(): User
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->forceFill(['permissions' => ['orders' => ['view' => true, 'manage' => false, 'export' => false]]])->save();

        return $editor->fresh();
    }

    private function bankTransferOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'RTB-OPS-1',
            'customer_name' => 'Test Customer',
            'customer_phone' => '0512345678',
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh', 'district' => 'Al Malqa'],
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::BankTransfer,
            'subtotal' => 9,
            'shipping_fee' => 25,
            'total' => 34,
        ], $overrides));
    }

    public function test_staff_can_record_a_bank_transfer(): void
    {
        $admin = $this->admin();
        $order = $this->bankTransferOrder();

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->order_number}/transfer-received", ['reference' => 'ALR-778'])
            ->assertRedirect()
            ->assertSessionHas('success', __('messages.admin.transfer_received'));

        $order->refresh();
        $this->assertSame(OrderStatus::AwaitingConfirmation, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->paid_at);

        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame('bank_transfer', $payment->gateway);
        $this->assertSame('succeeded', $payment->status);
        $this->assertSame('ALR-778', $payment->gateway_transaction_id);
        $this->assertEquals(34, (float) $payment->amount);

        // The timeline says who recorded it, since a person did, not a gateway.
        $activity = OrderActivity::where('order_id', $order->id)->where('type', 'payment_received')->sole();
        $this->assertSame($admin->id, $activity->user_id);
        $this->assertSame('bank_transfer', $activity->meta['gateway']);
        $this->assertSame('awaiting_confirmation', $activity->to_status);
    }

    /** A double click, or two staff at once, must not record the money twice. */
    public function test_a_transfer_is_recorded_once(): void
    {
        $admin = $this->admin();
        $order = $this->bankTransferOrder();

        $this->actingAs($admin)->post("/admin/orders/{$order->order_number}/transfer-received");
        $this->actingAs($admin)
            ->post("/admin/orders/{$order->order_number}/transfer-received")
            ->assertSessionHas('error', __('messages.admin.transfer_not_applicable'));

        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_a_card_order_cannot_be_marked_as_a_transfer(): void
    {
        $order = $this->bankTransferOrder(['payment_method' => PaymentMethod::Card]);

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->order_number}/transfer-received")
            ->assertSessionHas('error');

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(0, Payment::where('order_id', $order->id)->count());
    }

    public function test_the_transfer_action_is_offered_only_while_a_transfer_is_awaited(): void
    {
        Setting::set('bank_name', 'مصرف الراجحي');
        $admin = $this->admin();
        $order = $this->bankTransferOrder();

        $page = $this->actingAs($admin)->get("/admin/orders/{$order->order_number}")->inertiaPage()['props'];
        $this->assertTrue($page['can']['markTransferReceived']);
        $this->assertSame('مصرف الراجحي', $page['order']['bank_name']);

        $order->forceFill(['payment_status' => PaymentStatus::Paid, 'status' => OrderStatus::AwaitingConfirmation])->save();

        $page = $this->actingAs($admin)->get("/admin/orders/{$order->order_number}")->inertiaPage()['props'];
        $this->assertFalse($page['can']['markTransferReceived']);
        $this->assertNull($page['order']['bank_name']);
    }

    /**
     * Every action posts behind `orders.manage`, so a view-only editor must not
     * be offered any of them. Before, they got the buttons and a row of 403s.
     */
    public function test_a_view_only_editor_is_offered_no_actions(): void
    {
        $editor = $this->viewOnlyEditor();
        $order = $this->bankTransferOrder(['status' => OrderStatus::AwaitingConfirmation, 'payment_status' => PaymentStatus::Paid]);

        $can = $this->actingAs($editor)->get("/admin/orders/{$order->order_number}")->assertOk()->inertiaPage()['props']['can'];

        foreach ($can as $action => $allowed) {
            $this->assertFalse($allowed, "a view-only editor was offered `{$action}`");
        }

        $this->actingAs($editor)->post("/admin/orders/{$order->order_number}/transfer-received")->assertForbidden();
        $this->actingAs($editor)->post("/admin/orders/{$order->order_number}/notes", ['admin_notes' => 'x'])->assertForbidden();
    }

    public function test_staff_notes_are_saved_and_cleared(): void
    {
        $admin = $this->admin();
        $order = $this->bankTransferOrder();

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->order_number}/notes", ['admin_notes' => '  Customer asked for a gift note.  '])
            ->assertSessionHas('success', __('messages.admin.notes_saved'));
        $this->assertSame('Customer asked for a gift note.', $order->fresh()->admin_notes);

        $this->actingAs($admin)->post("/admin/orders/{$order->order_number}/notes", ['admin_notes' => '']);
        $this->assertNull($order->fresh()->admin_notes);
    }

    /** The unavailable reason used to overwrite whatever staff had written. */
    public function test_marking_unavailable_appends_to_existing_notes(): void
    {
        $order = $this->bankTransferOrder([
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid,
            'admin_notes' => 'Called the customer.',
        ]);

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->order_number}/unavailable", ['note' => 'Out of stock until Sunday.'])
            ->assertSessionHas('success');

        $this->assertSame("Called the customer.\n\nOut of stock until Sunday.", $order->fresh()->admin_notes);
    }

    public function test_the_packing_slip_lists_items_and_address_without_prices(): void
    {
        $order = $this->bankTransferOrder();
        OrderItem::create([
            'order_id' => $order->id,
            'product_name_ar' => 'شابورة بالبر',
            'option_label_ar' => 'كرتون',
            'sku' => 'RTB-0061',
            'unit_price' => 123.45,
            'quantity' => 3,
            'line_total' => 370.35,
        ]);

        $this->actingAs($this->viewOnlyEditor())
            ->get("/admin/orders/{$order->order_number}/packing-slip")
            ->assertOk()
            ->assertSee('RTB-OPS-1')
            ->assertSee('شابورة بالبر')
            ->assertSee('كرتون')
            ->assertSee('RTB-0061')
            ->assertSee('Al Malqa')
            ->assertDontSee('123.45')
            ->assertDontSee('370.35');
    }

    public function test_the_order_carries_whatsapp_and_tracking_links(): void
    {
        ShippingCarrier::create([
            'key' => ShippingCarrier::normalizeKey('SMSA'),
            'name' => 'SMSA',
            'tracking_url' => 'https://track.example/{tracking}',
        ]);
        $order = $this->bankTransferOrder([
            'status' => OrderStatus::Shipped,
            'payment_status' => PaymentStatus::Paid,
            'carrier' => 'SMSA Express',
            'tracking_number' => 'TRK 9',
        ]);

        $props = $this->actingAs($this->admin())->get("/admin/orders/{$order->order_number}")->inertiaPage()['props']['order'];

        // The order was placed with a LOCAL number. wa.me needs the country code,
        // or the link opens no chat at all.
        $this->assertSame('https://wa.me/966512345678', $props['whatsapp_url']);
        $this->assertSame('https://track.example/TRK%209', $props['tracking_url']);
    }
}
