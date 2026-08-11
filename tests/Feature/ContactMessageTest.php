<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = [
        'first_name' => 'سارة',
        'last_name' => 'العتيبي',
        'email' => 'sara@example.com',
        'phone' => '0555555555',
        'inquiry_type' => 'product',
        'message' => 'أرغب بمعرفة توفر تمور العجوة قبل رمضان.',
    ];

    private function staff(): User
    {
        return User::forceCreate(['name' => 'Admin', 'email' => 'a'.uniqid().'@test.com', 'password' => bcrypt('x'), 'role' => 'admin']);
    }

    public function test_guest_submission_is_stored_and_staff_are_notified(): void
    {
        Notification::fake();
        $admin = $this->staff();

        $this->post('/contact', $this->payload)->assertRedirect();

        $this->assertDatabaseHas('contact_messages', [
            'first_name' => 'سارة',
            'email' => 'sara@example.com',
            'inquiry_type' => 'product',
        ]);
        Notification::assertSentTo($admin, ContactMessageReceivedNotification::class);
    }

    public function test_it_records_the_submitter_ip(): void
    {
        $this->post('/contact', $this->payload)->assertRedirect();

        $this->assertSame('127.0.0.1', ContactMessage::first()->ip);
    }

    public function test_signed_in_customer_can_submit_without_a_turnstile_token(): void
    {
        Notification::fake();
        $this->staff();
        $customer = User::forceCreate(['name' => 'Zaid', 'email' => 'zaid@example.com', 'password' => bcrypt('x')]);

        $this->actingAs($customer)->post('/contact', $this->payload)->assertRedirect();

        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_missing_required_fields_are_rejected(): void
    {
        $this->post('/contact', [])->assertSessionHasErrors(['first_name', 'last_name', 'email', 'phone', 'inquiry_type', 'message']);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_email_must_be_a_valid_address(): void
    {
        $this->post('/contact', array_merge($this->payload, ['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');
    }

    public function test_inquiry_type_must_be_one_of_the_known_options(): void
    {
        $this->post('/contact', array_merge($this->payload, ['inquiry_type' => 'made-up']))
            ->assertSessionHasErrors('inquiry_type');
    }

    public function test_every_known_inquiry_type_is_accepted(): void
    {
        foreach (['order', 'product', 'complaint', 'partnership', 'other'] as $type) {
            $this->post('/contact', array_merge($this->payload, ['inquiry_type' => $type]))->assertSessionDoesntHaveErrors();
        }
        $this->assertDatabaseCount('contact_messages', 5);
    }

    /**
     * Pins the throttle-bucket-prefix convention (CLAUDE.md gotcha: an unprefixed
     * throttle silently shares a counter with every other public POST route).
     */
    public function test_the_contact_route_has_its_own_named_throttle_bucket(): void
    {
        $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->uri() === 'contact' && in_array('POST', $r->methods()));

        $this->assertNotNull($route);
        $throttle = collect($route->middleware())->first(fn ($m) => str_starts_with($m, 'throttle:'));
        $this->assertSame('throttle:5,1,contact-submit', $throttle);
    }

    public function test_staff_without_an_email_only_get_the_bell_row(): void
    {
        Notification::fake();
        $phoneOnlyStaff = User::forceCreate(['name' => 'WhatsApp Admin', 'phone' => '0500000000', 'password' => bcrypt('x'), 'role' => 'admin']);

        $this->post('/contact', $this->payload)->assertRedirect();

        Notification::assertSentTo(
            $phoneOnlyStaff,
            ContactMessageReceivedNotification::class,
            fn ($notification, $channels) => $channels === ['database'],
        );
    }
}
