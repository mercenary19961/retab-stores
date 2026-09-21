<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The strip above the storefront, managed at /admin/announcements.
 *
 * Built for the client's real case: "stuffed dates ship to Riyadh only". ⚠️ It
 * announces, it does not enforce — see the Announcement model.
 */
class AnnouncementController extends Controller
{
    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            // Arabic required, English optional and falling back to Arabic — the
            // same bilingual contract the catalogue uses.
            'message_ar' => ['required', 'string', 'max:500'],
            'message_en' => ['nullable', 'string', 'max:500'],
            // ⚠️ `url` rather than free text: this renders as a real link on the
            // storefront, and "www.example.com" without a scheme resolves to a
            // path on our own site rather than off it.
            'link_url' => ['nullable', 'url', 'max:255'],
            'link_label_ar' => ['nullable', 'string', 'max:120'],
            'link_label_en' => ['nullable', 'string', 'max:120'],
            'tone' => ['required', Rule::in(Announcement::TONES)],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            // 🔑 An end before the start would create a banner that can never
            // show, with nothing on the page explaining why.
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function index(): Response
    {
        return Inertia::render('admin/announcements/index', [
            'announcements' => Announcement::orderBy('sort_order')->latest()->get()->map(fn (Announcement $a) => [
                'id' => $a->id,
                'message_ar' => $a->message_ar,
                'message_en' => $a->message_en,
                'link_url' => $a->link_url,
                'link_label_ar' => $a->link_label_ar,
                'link_label_en' => $a->link_label_en,
                'tone' => $a->tone,
                'is_active' => $a->is_active,
                // ⚠️ Shipped as ISO so the client can render it in the admin's own
                // language and timezone. The panel stores and shows UTC today
                // (a known, panel-wide issue — see the 2026-09-13 entry).
                'starts_at' => $a->starts_at?->toIso8601String(),
                'ends_at' => $a->ends_at?->toIso8601String(),
                'sort_order' => $a->sort_order,
                'state' => $a->state(),
            ]),
            'tones' => Announcement::TONES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Announcement::create($this->payload($request));

        return back()->with('success', __('messages.admin.announcement_saved'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->payload($request));

        return back()->with('success', __('messages.admin.announcement_saved'));
    }

    /**
     * One-click on/off from the list.
     *
     * 🔑 This IS the client's "leave it up until I hide it": an announcement with
     * no end date runs until somebody switches it off here. No confirm step —
     * it is trivially reversible, matching the panel's other status toggles.
     */
    public function toggle(Announcement $announcement): RedirectResponse
    {
        $announcement->update(['is_active' => ! $announcement->is_active]);

        return back();
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', __('messages.admin.announcement_deleted'));
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $data = $request->validate($this->rules());

        // An unchecked checkbox is absent from the request, not false.
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
