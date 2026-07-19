<?php

namespace App\Jobs;

use App\Models\WhatsappCampaign;
use App\Services\WhatsApp\CampaignService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a queued campaign to the opt-in segment in chunks. WhatsAppService
 * swallows per-recipient transport failures into `failed` ledger rows, so one
 * bad number never aborts the blast. Re-running is safe-ish (rows would
 * duplicate) — hence the `sending` status gate in CampaignService.
 */
class SendWhatsappCampaign implements ShouldQueue
{
    use Queueable;

    /** Give a large segment room to finish. */
    public int $timeout = 600;

    public function __construct(
        public int $campaignId,
    ) {}

    public function handle(WhatsAppService $whatsapp, CampaignService $campaigns): void
    {
        $campaign = WhatsappCampaign::with('template')->find($this->campaignId);
        if (! $campaign || $campaign->status !== 'sending') {
            return;
        }

        $campaigns->audience($campaign->segment)->chunkById(100, function ($users) use ($whatsapp, $campaign) {
            foreach ($users as $user) {
                $whatsapp->sendCampaignMessage($user, $campaign);
            }
        });

        $campaign->update(['status' => 'sent', 'sent_at' => now()]);
    }
}
