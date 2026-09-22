<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One slide in the homepage hero, managed by the client at /admin/hero.
 *
 * Two kinds, sharing a window and an on/off switch: a still IMAGE (2:1 desktop
 * art, optional 4:5 phone art) or a muted, looping VIDEO with a poster frame.
 *
 * 🔑 Separate from EventHeroBanner on purpose — see the migration. Which of the
 * two the storefront shows is the client's choice (`hero_event_mode`), not a rule
 * baked in here.
 *
 * @mixin IdeHelperHeroSlide
 */
class HeroSlide extends Model
{
    use SoftDeletes;

    public const KIND_IMAGE = 'image';

    public const KIND_VIDEO = 'video';

    /** @var list<string> */
    public const KINDS = [self::KIND_IMAGE, self::KIND_VIDEO];

    protected $fillable = [
        'kind', 'image', 'image_mobile', 'video', 'video_poster', 'focal_x', 'focal_y',
        'href', 'alt_ar', 'alt_en',
        'is_active', 'starts_at', 'ends_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function isVideo(): bool
    {
        return $this->kind === self::KIND_VIDEO;
    }

    /**
     * Does this slide have the file it needs in order to render anything?
     *
     * 🔴 A video slide whose upload failed, or an image slide whose art was
     * deleted, would paint an empty box across the top of the homepage. Same
     * discipline as Product::isPublishable() and EventHeroBanner requiring its
     * linked offer to still be live: incomplete means hidden, never broken.
     */
    public function isRenderable(): bool
    {
        return $this->isVideo() ? filled($this->video) : filled($this->image);
    }

    /**
     * Showing on the storefront right now.
     *
     * 🔑 A NULL date means "no bound on that side", so a slide with no `ends_at`
     * simply runs until the client switches it off — the same rule the
     * announcements use, and the reason neither needs a second "keep it up" flag.
     *
     * The renderability check is in SQL here and in PHP in state(), and the two
     * MUST agree; see state().
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            // The SQL twin of isRenderable().
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $v) => $v->where('kind', self::KIND_VIDEO)->whereNotNull('video')->where('video', '!=', ''))
                ->orWhere(fn (Builder $i) => $i->where('kind', '!=', self::KIND_VIDEO)->whereNotNull('image')->where('image', '!=', '')));
    }

    /**
     * What the admin list shows in its status pill.
     *
     * 🔴 Deliberately MIRRORS scopeLive() rather than re-deciding. A row the admin
     * page calls "live" that the storefront does not show reads as a bug in the
     * storefront, and this is exactly the page where the client checks their own
     * work. A test walks every slide and asserts the two sets are identical.
     *
     * Order matters: `off` beats the dates because it is the manual override, and
     * `incomplete` beats everything because nothing else is actionable until the
     * file is there.
     */
    public function state(): string
    {
        if (! $this->isRenderable()) {
            return 'incomplete';
        }
        if (! $this->is_active) {
            return 'off';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'scheduled';
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'ended';
        }

        return 'live';
    }

    /** `object-position` for the art, e.g. "50% 35%". */
    public function focalPosition(): string
    {
        return ((int) ($this->focal_x ?? 50)).'% '.((int) ($this->focal_y ?? 50)).'%';
    }

    /**
     * The shape the storefront hero consumes. Kept next to the model so the
     * admin preview and the real homepage are fed by ONE definition and cannot
     * drift — which is the whole point of the preview.
     *
     * @return array<string, mixed>
     */
    public function toHeroPayload(): array
    {
        return [
            'id' => 'slide-'.$this->id,
            'kind' => $this->kind,
            // Video keeps its poster in `image` so a consumer that only knows how
            // to paint a still (the SSR pass, reduced motion) still has one.
            'image' => Media::url($this->isVideo() ? $this->video_poster : $this->image, 'hero'),
            'image_mobile' => $this->image_mobile ? Media::url($this->image_mobile, 'detail') : null,
            // ⚠️ NOT variant-mapped: the variant pipeline only produces WebP
            // stills, so asking for one here would hand the player a 404.
            'video' => $this->isVideo() ? Media::url($this->video) : null,
            // CSS `object-position`, so the crop keeps whatever the client
            // clicked rather than whatever happened to be in the middle.
            'focal' => $this->focalPosition(),
            'href' => $this->href,
            'alt_ar' => $this->alt_ar,
            'alt_en' => $this->alt_en,
        ];
    }
}
