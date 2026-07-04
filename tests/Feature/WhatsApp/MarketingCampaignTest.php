<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingCampaignTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('secret'), 'role' => 'admin']);
    }

    private function approvedTemplate(array $overrides = []): WhatsappTemplate
    {
        return WhatsappTemplate::create(array_merge([
            'name' => 'monthly_offer',
            'language' => 'ar',
            'category' => 'marketing',
            'body' => 'عرض الشهر: {{1}}',
            'param_count' => 1,
            'status' => 'approved',
        ], $overrides));
    }

    public function test_campaign_reaches_only_opted_in_customers_with_phones(): void
    {
        $admin = $this->admin(); // staff with a phone must be excluded too
        $admin->forceFill(['phone' => '966599999999', 'whatsapp_opt_in' => true])->save();

        User::create(['name' => 'In', 'email' => 'in@test.com', 'password' => bcrypt('x'), 'phone' => '966500000001', 'whatsapp_opt_in' => true]);
        User::create(['name' => 'NoPhone', 'email' => 'np@test.com', 'password' => bcrypt('x'), 'whatsapp_opt_in' => true]);
        User::create(['name' => 'Out', 'email' => 'out@test.com', 'password' => bcrypt('x'), 'phone' => '966500000002', 'whatsapp_opt_in' => false]);

        $template = $this->approvedTemplate();

        $this->actingAs($admin)->post('/admin/marketing/campaigns', [
            'whatsapp_template_id' => $template->id,
            'params' => ['خصم ١٥٪'],
        ])->assertSessionHas('success');

        $campaign = WhatsappCampaign::firstOrFail();
        // QUEUE_CONNECTION=sync in tests → the job already ran.
        $this->assertSame('sent', $campaign->fresh()->status);
        $this->assertSame(1, $campaign->audience_count);
        $this->assertDatabaseCount('whatsapp_messages', 1);
        $this->assertDatabaseHas('whatsapp_messages', [
            'campaign_id' => $campaign->id,
            'recipient' => '966500000001',
            'template' => 'monthly_offer',
            'category' => 'marketing',
        ]);
    }

    public function test_unapproved_template_cannot_be_sent(): void
    {
        User::create(['name' => 'In', 'email' => 'in@test.com', 'password' => bcrypt('x'), 'phone' => '966500000001', 'whatsapp_opt_in' => true]);
        $template = $this->approvedTemplate(['status' => 'pending']);

        $this->actingAs($this->admin())->post('/admin/marketing/campaigns', [
            'whatsapp_template_id' => $template->id,
            'params' => ['x'],
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('whatsapp_campaigns', 0); // dead draft cleaned up
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_param_count_mismatch_is_rejected(): void
    {
        $template = $this->approvedTemplate(); // expects 1 param

        $this->actingAs($this->admin())->post('/admin/marketing/campaigns', [
            'whatsapp_template_id' => $template->id,
            'params' => [],
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('whatsapp_campaigns', 0);
    }
}
