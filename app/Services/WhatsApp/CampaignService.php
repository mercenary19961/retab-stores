<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsappCampaign;
use App\Models\User;
use App\Models\WhatsappCampaign;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Campaign orchestration + the compliance guardrails from the client brief:
 * marketing goes ONLY to opted-in customers with a phone, and ONLY through a
 * Meta-APPROVED template (business-initiated messages outside the 24h window
 * must be approved templates). Sending is queued — a big segment must not tie
 * up an admin request.
 */
class CampaignService
{
    /** The opt-in marketing segment: consented customers with a phone. */
    public function audience(): Builder
    {
        return User::where('whatsapp_opt_in', true)
            ->whereNotNull('phone')
            ->where(fn ($q) => $q->whereNull('role')->orWhereNotIn('role', ['admin', 'editor']));
    }

    /**
     * Queue a draft campaign for sending. Snapshot the audience count now so
     * the admin sees what the blast targeted even as opt-ins change later.
     *
     * @throws RuntimeException on guardrail violations
     */
    public function send(WhatsappCampaign $campaign, ?int $adminId = null): WhatsappCampaign
    {
        if ($campaign->status !== 'draft') {
            throw new RuntimeException(__('messages.marketing.already_sent'));
        }
        if (! $campaign->template->isApproved()) {
            throw new RuntimeException(__('messages.marketing.template_not_approved'));
        }

        $count = (clone $this->audience())->count();
        if ($count === 0) {
            throw new RuntimeException(__('messages.marketing.no_audience'));
        }

        $campaign->update([
            'status' => 'sending',
            'audience_count' => $count,
            'sent_by' => $adminId,
        ]);

        SendWhatsappCampaign::dispatch($campaign->id);

        return $campaign;
    }
}
