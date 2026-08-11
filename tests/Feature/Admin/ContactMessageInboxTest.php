<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\ContactMessageController;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactMessageInboxTest extends TestCase
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

    private function message(array $overrides = []): ContactMessage
    {
        return ContactMessage::create(array_merge([
            'first_name' => 'سارة',
            'last_name' => 'العتيبي',
            'email' => 'sara@example.com',
            'phone' => '0555555555',
            'inquiry_type' => 'product',
            'message' => 'أرغب بمعرفة توفر تمور العجوة قبل رمضان.',
        ], $overrides));
    }

    public function test_the_inbox_lists_messages_with_their_body(): void
    {
        $this->message();

        $this->actingAs($this->admin())->get('/admin/contact-messages')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/contact-messages/index')
                ->where('messages.data.0.name', 'سارة العتيبي')
                // The body is the whole point of this page — the bell doesn't carry it.
                ->where('messages.data.0.message', 'أرغب بمعرفة توفر تمور العجوة قبل رمضان.')
                ->where('messages.data.0.handled', false)
                ->where('openCount', 1),
            );
    }

    public function test_it_builds_a_whatsapp_reply_link_from_the_normalized_phone(): void
    {
        $this->message(['phone' => '+966 55 555 5555']);

        $this->actingAs($this->admin())->get('/admin/contact-messages')
            ->assertInertia(fn ($page) => $page->where('messages.data.0.whatsapp_url', 'https://wa.me/966555555555'));
    }

    /** The filter list must come from the storefront's own validated set, not a copy. */
    public function test_the_inquiry_type_filter_list_matches_the_forms_validated_set(): void
    {
        $this->actingAs($this->admin())->get('/admin/contact-messages')
            ->assertInertia(fn ($page) => $page->where(
                'inquiryTypes',
                ContactMessageController::INQUIRY_TYPES,
            ));
    }

    public function test_it_can_be_filtered_to_open_or_handled(): void
    {
        $this->message(['email' => 'open@example.com']);
        $this->message(['email' => 'done@example.com', 'handled_at' => now()]);

        $this->actingAs($this->admin())->get('/admin/contact-messages?status=open')
            ->assertInertia(fn ($page) => $page->has('messages.data', 1)->where('messages.data.0.email', 'open@example.com'));

        $this->actingAs($this->admin())->get('/admin/contact-messages?status=handled')
            ->assertInertia(fn ($page) => $page->has('messages.data', 1)->where('messages.data.0.email', 'done@example.com'));
    }

    public function test_marking_a_message_handled_stamps_it(): void
    {
        $message = $this->message();

        $this->actingAs($this->admin())
            ->post("/admin/contact-messages/{$message->id}/handle")
            ->assertRedirect();

        $this->assertNotNull($message->fresh()->handled_at);
    }

    public function test_an_editor_without_the_view_permission_is_blocked(): void
    {
        $editor = $this->editor(['contact_messages' => ['view' => false, 'manage' => false]]);

        $this->actingAs($editor)->get('/admin/contact-messages')->assertForbidden();
    }

    public function test_an_editor_with_view_but_not_manage_cannot_mark_handled(): void
    {
        $editor = $this->editor(['contact_messages' => ['view' => true, 'manage' => false]]);
        $message = $this->message();

        $this->actingAs($editor)->get('/admin/contact-messages')->assertOk();
        $this->actingAs($editor)->post("/admin/contact-messages/{$message->id}/handle")->assertForbidden();

        $this->assertNull($message->fresh()->handled_at);
    }

    public function test_customers_cannot_reach_the_inbox(): void
    {
        $customer = User::forceCreate(['name' => 'Zaid', 'email' => 'z@example.com', 'password' => bcrypt('x')]);

        $this->actingAs($customer)->get('/admin/contact-messages')->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin/contact-messages')->assertRedirect('/login');
    }

    /**
     * The notification used to ship no `url`, so a click fell through to the
     * dashboard and the message was unreadable in the panel. Pins the deep link.
     */
    public function test_the_notification_deep_links_to_the_inbox(): void
    {
        $admin = $this->admin();
        $this->post('/contact', [
            'first_name' => 'سارة', 'last_name' => 'العتيبي', 'email' => 'sara@example.com',
            'phone' => '0555555555', 'inquiry_type' => 'product', 'message' => 'استفسار.',
        ])->assertRedirect();

        $notification = $admin->fresh()->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('/admin/contact-messages', $notification->data['url']);

        $this->actingAs($admin)->get("/admin/notifications/{$notification->id}")
            ->assertRedirect('/admin/contact-messages');
    }
}
