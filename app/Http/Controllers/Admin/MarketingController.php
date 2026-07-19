<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * WhatsApp marketing (client brief): a template registry mirroring Meta
 * Business Manager (authored + approved THERE; status synced manually here for
 * now) and a campaign sender to the opt-in segment. Delivery stats come from
 * the whatsapp_messages ledger via the campaign_id link.
 */
class MarketingController extends Controller
{
    public function __construct(
        protected CampaignService $campaigns,
    ) {}

    public function index()
    {
        return Inertia::render('admin/marketing/index', [
            'templates' => WhatsappTemplate::orderBy('name')->get()
                ->map(fn (WhatsappTemplate $t) => $t->only('id', 'name', 'language', 'category', 'body', 'param_count', 'status')),
            'campaigns' => WhatsappCampaign::with('template:id,name,language')
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (WhatsappCampaign $c) => [
                    'id' => $c->id,
                    'template' => $c->template?->name,
                    'params' => $c->params,
                    'status' => $c->status,
                    'audience_count' => $c->audience_count,
                    'sent_at' => $c->sent_at?->toDateTimeString(),
                    'stats' => $c->status === 'draft' ? [] : $c->stats(),
                ]),
            // Recipient count per selectable segment → the composer shows a live
            // "Send to N" as the owner switches segment.
            'segmentCounts' => $this->campaigns->segmentCounts(),
            'segments' => CampaignService::SEGMENTS,
        ]);
    }

    public function storeTemplate(Request $request)
    {
        $data = $this->validateTemplate($request);

        WhatsappTemplate::create($data);

        return back()->with('success', __('messages.marketing.template_saved'));
    }

    public function updateTemplate(Request $request, WhatsappTemplate $template)
    {
        $template->update($this->validateTemplate($request, $template));

        return back()->with('success', __('messages.marketing.template_saved'));
    }

    public function storeCampaign(Request $request)
    {
        $data = $request->validate([
            'whatsapp_template_id' => ['required', 'integer', 'exists:whatsapp_templates,id'],
            'params' => ['array'],
            'params.*' => ['required', 'string', 'max:255'],
            'segment' => ['nullable', \Illuminate\Validation\Rule::in(CampaignService::SEGMENTS)],
        ]);

        $template = WhatsappTemplate::findOrFail($data['whatsapp_template_id']);

        if (count($data['params'] ?? []) !== $template->param_count) {
            return back()->with('error', __('messages.marketing.params_mismatch'));
        }

        $campaign = WhatsappCampaign::create([
            'whatsapp_template_id' => $template->id,
            'params' => array_values($data['params'] ?? []),
            // Explicit, not the column default: create() doesn't hydrate DB
            // defaults, and CampaignService's status gate reads the model.
            'segment' => $data['segment'] ?? 'all',
            'status' => 'draft',
        ]);

        try {
            $this->campaigns->send($campaign, Auth::id());
        } catch (\RuntimeException $e) {
            $campaign->delete(); // failed guardrails — don't leave a dead draft

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('messages.marketing.campaign_queued'));
    }

    /** @return array<string, mixed> */
    private function validateTemplate(Request $request, ?WhatsappTemplate $template = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                // Mirrors the DB's composite unique (name+language) so a
                // duplicate validates cleanly instead of throwing.
                \Illuminate\Validation\Rule::unique('whatsapp_templates', 'name')
                    ->where('language', (string) $request->input('language'))
                    ->ignore($template?->id),
            ],
            'language' => ['required', 'in:ar,en'],
            'category' => ['required', 'in:marketing,utility'],
            'body' => ['required', 'string', 'max:2000'],
            'param_count' => ['required', 'integer', 'min:0', 'max:10'],
            'status' => ['required', 'in:' . implode(',', WhatsappTemplate::STATUSES)],
        ]);
    }
}
