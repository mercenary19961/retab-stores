<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsappCampaign;
use App\Models\User;
use App\Models\WhatsappCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
    /**
     * Selectable audience segments. Each is an ADDITIVE filter on top of the
     * opt-in base — the compliance rule (opted in + has phone + not staff)
     * always holds. 'all' is the base itself. Order = display order.
     */
    public const SEGMENTS = ['all', 'active', 'recent', 'repeat', 'dormant'];

    /**
     * The opt-in marketing base: consented customers with a phone (never staff).
     * This is the compliance floor every segment builds on.
     */
    public function baseAudience(): Builder
    {
        return User::where('whatsapp_opt_in', true)
            ->whereNotNull('phone')
            ->where(fn ($q) => $q->whereNull('role')->orWhereNotIn('role', ['admin', 'editor']));
    }

    /**
     * The opt-in base narrowed to a segment. The "top/recent half" segments use
     * a median threshold (computed over the current base) so they stay ~half as
     * the list grows, and remain a plain WHERE clause the send job can chunk.
     */
    public function audience(?string $segment = null): Builder
    {
        $q = $this->baseAudience();

        return match ($segment) {
            'active' => $q->where('confirmed_purchases_count', '>=', $this->medianPurchases()),
            'recent' => ($since = $this->medianSignup()) ? $q->where('created_at', '>=', $since) : $q,
            'repeat' => $q->where('confirmed_purchases_count', '>=', 2),
            'dormant' => $q->where('confirmed_purchases_count', 0),
            default => $q, // 'all' | null
        };
    }

    /**
     * Recipient count per segment, for the composer's live "Send to N" preview.
     *
     * @return array<string, int>
     */
    public function segmentCounts(): array
    {
        $counts = [];
        foreach (self::SEGMENTS as $segment) {
            $counts[$segment] = $this->audience($segment)->count();
        }

        return $counts;
    }

    /** Median confirmed-purchases over the base — the "top half" threshold. */
    private function medianPurchases(): int
    {
        $values = $this->baseAudience()->orderBy('confirmed_purchases_count')->pluck('confirmed_purchases_count');

        return $values->isEmpty() ? 0 : (int) $values->get((int) floor(($values->count() - 1) / 2));
    }

    /** Median signup timestamp over the base — the "newest half" threshold. */
    private function medianSignup(): ?Carbon
    {
        $values = $this->baseAudience()->orderBy('created_at')->pluck('created_at');

        return $values->isEmpty() ? null : $values->get((int) floor(($values->count() - 1) / 2));
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

        $count = $this->audience($campaign->segment)->count();
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
