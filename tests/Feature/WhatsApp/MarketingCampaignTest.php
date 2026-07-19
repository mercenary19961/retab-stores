<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\CampaignService;
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

    private function optedIn(string $email, ?string $phone, int $purchases, bool $optIn = true): User
    {
        return User::create([
            'name' => $email, 'email' => $email, 'password' => bcrypt('x'),
            'phone' => $phone, 'whatsapp_opt_in' => $optIn, 'confirmed_purchases_count' => $purchases,
        ]);
    }

    public function test_segments_narrow_the_opt_in_audience(): void
    {
        $svc = app(CampaignService::class);

        $this->optedIn('a@t.com', '966500000001', 3);           // repeat
        $this->optedIn('b@t.com', '966500000002', 2);           // repeat
        $this->optedIn('c@t.com', '966500000003', 1);           // neither
        $this->optedIn('d@t.com', '966500000004', 0);           // dormant
        $this->optedIn('out@t.com', '966500000005', 9, false);  // opted out → excluded
        $this->optedIn('nophone@t.com', null, 9);               // no phone → excluded

        $this->assertSame(4, $svc->audience('all')->count());
        $this->assertSame(2, $svc->audience('repeat')->count());
        $this->assertSame(1, $svc->audience('dormant')->count());
    }

    public function test_active_segment_targets_the_more_active_half_and_excludes_opt_outs(): void
    {
        $svc = app(CampaignService::class);

        $low = $this->optedIn('low@t.com', '966500000001', 0);
        $mid = $this->optedIn('mid@t.com', '966500000002', 4);
        $high = $this->optedIn('high@t.com', '966500000003', 5);
        $this->optedIn('out@t.com', '966500000004', 9, false); // opted out → excluded despite high activity

        $ids = $svc->audience('active')->pluck('id');

        $this->assertSame(2, $ids->count());
        $this->assertTrue($ids->contains($mid->id));
        $this->assertTrue($ids->contains($high->id));
        $this->assertFalse($ids->contains($low->id));
    }

    public function test_campaign_sends_only_to_its_segment(): void
    {
        $this->optedIn('a@t.com', '966500000001', 3); // repeat
        $this->optedIn('b@t.com', '966500000002', 2); // repeat
        $this->optedIn('c@t.com', '966500000003', 0); // excluded from repeat
        $template = $this->approvedTemplate();

        $this->actingAs($this->admin())->post('/admin/marketing/campaigns', [
            'whatsapp_template_id' => $template->id,
            'params' => ['خصم'],
            'segment' => 'repeat',
        ])->assertSessionHas('success');

        $campaign = WhatsappCampaign::firstOrFail();
        $this->assertSame('repeat', $campaign->segment);
        $this->assertSame(2, $campaign->audience_count);
        $this->assertDatabaseCount('whatsapp_messages', 2);
        $this->assertDatabaseMissing('whatsapp_messages', ['recipient' => '966500000003']);
    }

    public function test_invalid_segment_is_rejected(): void
    {
        $this->optedIn('a@t.com', '966500000001', 1);
        $template = $this->approvedTemplate();

        $this->actingAs($this->admin())->post('/admin/marketing/campaigns', [
            'whatsapp_template_id' => $template->id,
            'params' => ['x'],
            'segment' => 'bogus',
        ])->assertSessionHasErrors('segment');

        $this->assertDatabaseCount('whatsapp_campaigns', 0);
    }
}
