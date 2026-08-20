<?php

namespace App\Jobs;

use App\Models\WhatsappCampaign;
use App\Services\WhatsApp\CampaignService;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a queued campaign to the opt-in segment in chunks. WhatsAppService
 * records a ledger row per recipient and hands each Meta call to its own
 * SendWhatsappMessage job, so one bad number never aborts the blast (and each
 * message retries independently rather than the whole blast). Re-running is
 * safe-ish (rows would duplicate) — hence the `sending` status gate in
 * CampaignService. Note the campaign flips to `sent` once every message is
 * ENQUEUED; per-recipient delivery is tracked on the ledger rows themselves.
 */
class SendWhatsappCampaign implements ShouldQueue
{
    use Queueable;

    /** Give a large segment room to finish. */
    public int $timeout = 600;

    public function __construct(
        public int $campaignId,
    ) {
        // The orchestrator belongs on bulk too, not just the messages it spawns:
        // it iterates the whole opt-in segment, so leaving it on the default queue
        // would block transactional sends for the length of the blast.
        $this->onQueue(Queues::BULK);
    }

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
