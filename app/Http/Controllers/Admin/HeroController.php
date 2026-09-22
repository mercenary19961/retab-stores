<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Models\Setting;
use App\Services\ChangeLog\ChangeLogService;
use App\Support\HeroBanners;
use App\Support\Media;
use App\Support\MediaTrash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * The homepage hero, managed at /admin/hero.
 *
 * 🔑 THE PREVIEW IS THE POINT, and it is built by calling HeroBanners::live() —
 * the exact method the storefront calls — rather than by re-deriving the result
 * here. A preview that computes the answer a second way is worse than no preview:
 * it would agree right up until the rule changed, and then confidently show the
 * client something the homepage does not do.
 *
 * ⚠️ Every write is a POST, including the update. The form carries files, and a
 * multipart PUT is not parsed by PHP — the same reason product images live on
 * their own POST endpoints rather than in the product's PUT form.
 */
class HeroController extends Controller
{
    private const DIR = 'hero';

    /** @return array<string, array<int, mixed>> */
    private function rules(bool $creating): array
    {
        $max = Media::videoMaxMb() * 1024;

        return [
            'kind' => ['required', Rule::in(HeroSlide::KINDS)],

            /*
             * 🔑 Required only on CREATE, and only for the kind that needs it: an
             * edit that changes the schedule must not force the client to
             * re-upload artwork that is already there.
             *
             * 🔴 `nullable` is ALWAYS present, never swapped out for `required_if`.
             * The form posts every field it knows about, so an image slide still
             * sends an empty `video` — and without `nullable` that empty value
             * reaches the `file` rule and the whole form is rejected with "The
             * video field must be a file." Found in a browser; the unit test had
             * omitted the key entirely, which is not what a real form does.
             */
            'image' => array_values(array_filter([
                $creating ? 'required_if:kind,image' : null,
                'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192',
            ])),
            'image_mobile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'video' => array_values(array_filter([
                $creating ? 'required_if:kind,video' : null,
                'nullable', 'file', 'mimetypes:video/mp4,video/webm', "max:{$max}",
            ])),
            'video_poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],

            // ⚠️ Deliberately NOT the `url` rule: this is usually an internal path
            // ("/shop?event=3"), which `url` rejects. Left free text and rendered
            // through Inertia's <Link>, which treats it as a path.
            'href' => ['nullable', 'string', 'max:255'],

            'alt_ar' => ['nullable', 'string', 'max:255'],
            'alt_en' => ['nullable', 'string', 'max:255'],

            'is_active' => ['boolean'],
            // Where the 2:1 crop centres, as a percentage of the artwork.
            'focal_x' => ['nullable', 'integer', 'min:0', 'max:100'],
            'focal_y' => ['nullable', 'integer', 'min:0', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            // 🔑 An end before the start is a slide that can never show, with
            // nothing on the page to explain why. Same guard as announcements.
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function index(): Response
    {
        return Inertia::render('admin/hero/index', [
            'slides' => HeroSlide::orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (HeroSlide $s) => [
                    'id' => $s->id,
                    'kind' => $s->kind,
                    'image' => Media::url($s->image, 'card'),
                    'image_mobile' => Media::url($s->image_mobile, 'card'),
                    'video' => Media::url($s->video),
                    'video_poster' => Media::url($s->video_poster, 'card'),
                    // Full-size, for the crop preview: the `card` variant is 500px
                    // and would be judged for sharpness it was never meant to have.
                    'image_full' => Media::url($s->image),
                    'focal_x' => (int) $s->focal_x,
                    'focal_y' => (int) $s->focal_y,
                    'href' => $s->href,
                    'alt_ar' => $s->alt_ar,
                    'alt_en' => $s->alt_en,
                    'is_active' => $s->is_active,
                    // ISO so the panel renders it in its own language. ⚠️ The admin
                    // stores and shows UTC today — a known panel-wide issue.
                    'starts_at' => $s->starts_at?->toIso8601String(),
                    'ends_at' => $s->ends_at?->toIso8601String(),
                    'sort_order' => $s->sort_order,
                    'state' => $s->state(),
                ])->values(),

            // Read-only context: these belong to a campaign and are edited on
            // /admin/store-events. Shown here because they can take over this very
            // page's output, and a client wondering why their slide vanished needs
            // to see the reason on the page that is behaving unexpectedly.
            'campaignBanners' => HeroBanners::campaignBanners(),

            'mode' => HeroBanners::mode(),
            'modes' => HeroBanners::MODES,

            // 🔑 The real composed result, from the storefront's own method.
            'preview' => HeroBanners::live(),

            'videoMaxMb' => Media::videoMaxMb(),
            'canManage' => $this->canManage(),
        ]);
    }

    public function store(Request $request, ChangeLogService $changeLog): RedirectResponse
    {
        $data = $request->validate($this->rules(creating: true));

        $slide = new HeroSlide($this->attributes($data));

        try {
            $this->applyUploads($request, $slide);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        DB::transaction(function () use ($slide, $changeLog) {
            $slide->save();
            $changeLog->logCreated($slide, $this->label($slide));
        });

        return back()->with('success', __('messages.admin.hero_slide_saved'));
    }

    public function update(Request $request, HeroSlide $slide, ChangeLogService $changeLog): RedirectResponse
    {
        $data = $request->validate($this->rules(creating: false));

        // ⚠️ Captured BEFORE applyUploads, which writes the new paths onto the
        // model — so the entry records the artwork that was replaced, not the
        // artwork replacing it.
        $before = $slide->attributesToArray();

        $slide->fill($this->attributes($data));

        try {
            $this->applyUploads($request, $slide);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        DB::transaction(function () use ($slide, $before, $changeLog) {
            $slide->save();
            $changeLog->logUpdated($slide, $before, $this->label($slide));
        });

        return back()->with('success', __('messages.admin.hero_slide_saved'));
    }

    /**
     * Flip whether the slide is shown. No confirm step: it is one click to undo,
     * matching every other StatusToggle in the panel.
     */
    public function toggle(HeroSlide $slide, ChangeLogService $changeLog): RedirectResponse
    {
        DB::transaction(function () use ($slide, $changeLog) {
            $before = $slide->attributesToArray();
            $slide->update(['is_active' => ! $slide->is_active]);
            $changeLog->logUpdated($slide, $before, $this->label($slide));
        });

        return back()->with('success', __('messages.admin.hero_slide_saved'));
    }

    public function destroy(HeroSlide $slide, ChangeLogService $changeLog): RedirectResponse
    {
        /*
         * 🔴 THE FILES DELIBERATELY STAY. This used to Media::delete() the image,
         * phone art, video and poster before dropping the row, which made the
         * delete unrecoverable no matter what was written to the change log — a
         * revert needs the bytes, and the bytes were gone from R2 within the
         * second. The slide is soft-deleted instead, so Undo can put it back
         * whole, and media:purge-trash removes the artwork once the retention
         * window has closed. See App\Support\MediaTrash.
         */
        DB::transaction(function () use ($slide, $changeLog) {
            $changeLog->logDeleted($slide, $this->label($slide));
            $slide->delete();
        });

        return back()->with('success', __('messages.admin.hero_slide_deleted'));
    }

    /**
     * Swap a slide with its neighbour.
     *
     * ⚠️ A SWAP rather than a renumber, the same choice the categories page made:
     * renumbering from zero would rewrite rows the client did not touch, and the
     * order here is what the homepage rotates through.
     */
    public function reorder(Request $request, HeroSlide $slide, ChangeLogService $changeLog): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
        $before = $slide->attributesToArray();

        $neighbour = HeroSlide::query()
            ->where('id', '!=', $slide->id)
            ->when($direction === 'up',
                fn ($q) => $q->where('sort_order', '<=', $slide->sort_order)->orderByDesc('sort_order')->orderByDesc('id'),
                fn ($q) => $q->where('sort_order', '>=', $slide->sort_order)->orderBy('sort_order')->orderBy('id'))
            ->first();

        if (! $neighbour) {
            return back(); // already at the end — nothing to do
        }

        $mine = $slide->sort_order;
        $theirs = $neighbour->sort_order;

        /*
         * 🔴 A TIE IS THE NORMAL CASE, not an edge case: every new slide is
         * created at sort_order 0, so a client who adds three slides has three
         * zeroes and swapping two identical values moves nothing at all. The
         * first version clamped the mover to max(0, mine - 1), which is still 0
         * at the top of the list — so reordering looked completely dead.
         *
         * Push the OTHER row instead, which always separates them and never
         * needs a negative value.
         */
        /*
         * ⚠️ Only the MOVED slide is logged, though a swap writes two rows. Two
         * entries per click would bury the edits that matter in reorder noise,
         * and reverting one half of a swap is incoherent anyway — restoring this
         * slide's position is what "undo the move" means to the person who did
         * it. Ties are the ordinary state here (see above), so landing back on
         * one is harmless.
         */
        DB::transaction(function () use ($slide, $neighbour, $direction, $mine, $theirs, $before, $changeLog) {
            if ($mine === $theirs) {
                if ($direction === 'up') {
                    $neighbour->update(['sort_order' => $mine + 1]);
                } else {
                    $slide->update(['sort_order' => $mine + 1]);
                }
            } else {
                $slide->update(['sort_order' => $theirs]);
                $neighbour->update(['sort_order' => $mine]);
            }

            $changeLog->logUpdated($slide, $before, $this->label($slide));
        });

        return back();
    }

    /**
     * Apply a whole new order at once, which is what a drag-and-drop produces.
     *
     * 🔑 Kept alongside the one-step up/down buttons rather than replacing them:
     * dragging is mouse-only, and the buttons are the keyboard path. Removing
     * them would make reordering impossible without a pointer.
     *
     * ⚠️ Ids not belonging to a hero slide are simply ignored rather than
     * refused: the preview also contains CAMPAIGN banners, which live in another
     * table and are ordered by their event.
     */
    public function reorderAll(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
        ])['ids'];

        $slides = HeroSlide::whereIn('id', $ids)->get()->keyBy('id');

        $position = 0;
        foreach ($ids as $id) {
            $slide = $slides->get($id);
            if ($slide) {
                $slide->update(['sort_order' => $position++]);
            }
        }

        return back();
    }

    /**
     * Which source owns the hero while a campaign is running.
     *
     * The client asked to curate this rather than have a campaign silently take
     * the homepage over, so it is stored rather than hardcoded. See HeroBanners.
     */
    public function updateMode(Request $request, ChangeLogService $changeLog): RedirectResponse
    {
        $mode = $request->validate(['mode' => ['required', Rule::in(HeroBanners::MODES)]])['mode'];
        $key = HeroBanners::MODE_KEY;
        $current = (string) Setting::get($key, HeroBanners::MODES[0]);

        // 🔑 Logged like any other setting. This one decides whether a campaign
        // takes the homepage over, so "why did our slides stop showing?" has to be
        // answerable — and it was one of three places writing Setting::set()
        // outside SettingController, all of which bypassed the change log.
        if ($current === $mode) {
            return back()->with('success', __('messages.admin.hero_mode_saved'));
        }

        DB::transaction(function () use ($key, $current, $mode, $changeLog) {
            Setting::set($key, $mode);
            $changeLog->logSettingsUpdated([$key => $current], [$key => $mode]);
        });

        return back()->with('success', __('messages.admin.hero_mode_saved'));
    }

    /**
     * Non-file attributes. `kind` decides which of the two media pairs matters,
     * but the other pair is deliberately NOT cleared: a client who flips a slide
     * to video and back should find their artwork still there.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'kind' => $data['kind'],
            'href' => $data['href'] ?? null,
            'alt_ar' => $data['alt_ar'] ?? null,
            'alt_en' => $data['alt_en'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            // Clamped in validation; defaulted here so a slide saved before the
            // focal point existed keeps the centred crop it already had.
            'focal_x' => (int) ($data['focal_x'] ?? 50),
            'focal_y' => (int) ($data['focal_y'] ?? 50),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /**
     * Store whichever files were sent, replacing what they supersede.
     *
     * 🔑 The OLD file is deleted only once the NEW one has stored successfully.
     * The other order loses the artwork when an upload fails, leaving a slide that
     * renders nothing and a client who has to find the original again.
     *
     * @throws RuntimeException
     */
    private function applyUploads(Request $request, HeroSlide $slide): void
    {
        $images = ['image', 'image_mobile', 'video_poster'];

        /*
         * 🔴 The replaced file is QUEUED, not deleted. Deleting it here made the
         * change-log entry for this very edit a lie: reverting it writes the old
         * path back into the row, and the file it names would already be gone —
         * a slide pointing at a 404, which renders nothing at all. MediaTrash
         * removes it after the retention window, and only once nothing points at
         * it any more, so a revert inside the window silently keeps it.
         */
        foreach ($images as $field) {
            $file = $request->file($field);
            if ($file instanceof UploadedFile) {
                $old = $slide->{$field};
                $slide->{$field} = Media::storeImage($file, self::DIR);
                MediaTrash::schedule($old, "hero_slides.{$field}");
            }
        }

        $video = $request->file('video');
        if ($video instanceof UploadedFile) {
            $old = $slide->video;
            $slide->video = Media::storeVideo($video, self::DIR);
            MediaTrash::schedule($old, 'hero_slides.video');
        }
    }

    /**
     * What the change log calls this slide. A hero slide has no name, so the alt
     * text is the closest thing to one the client actually typed; failing that,
     * its kind and id, which at least distinguishes two rows in a list.
     */
    private function label(HeroSlide $slide): string
    {
        return $slide->alt_ar
            ?: ($slide->alt_en ?: ucfirst($slide->kind).' slide #'.$slide->getKey());
    }

    private function canManage(): bool
    {
        return (bool) request()->user()?->hasPermission('hero.manage');
    }
}
