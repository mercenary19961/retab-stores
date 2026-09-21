<?php

namespace App\Support;

use App\Models\EventHeroBanner;
use App\Models\HeroSlide;
use App\Models\Setting;

/**
 * What the homepage hero actually shows, consumed by `components/store/hero.tsx`.
 *
 * Two independent sources feed it:
 *  - CAMPAIGN banners, owned by a running store event (/admin/store-events).
 *    They inherit the campaign's window and leave the homepage with it.
 *  - The client's own SLIDES (/admin/hero), images or video, each with its own
 *    window and on/off switch.
 *
 * 🔑 Which of the two wins is the CLIENT'S choice, not a rule hardcoded here.
 * That was an explicit decision (2026-09-21): they wanted to curate the hero
 * rather than have a campaign silently take it over, and to see the result before
 * trusting it. `hero_event_mode` holds the choice and the admin page previews the
 * outcome by calling this very method, so the preview cannot drift from the page.
 *
 * Empty result ⇒ the hero falls back to its four built-in copy slides. That is
 * the shipped default and it needs no row in any table.
 */
class HeroBanners
{
    /** Campaign banners replace the client's slides entirely while one runs. */
    public const MODE_TAKEOVER = 'takeover';

    /** Campaign banners run first, then the client's slides, in one carousel. */
    public const MODE_MERGE = 'merge';

    /** The hero is the client's alone; campaigns never touch it. */
    public const MODE_MINE = 'mine';

    /** @var list<string> */
    public const MODES = [self::MODE_TAKEOVER, self::MODE_MERGE, self::MODE_MINE];

    public const MODE_KEY = 'hero_event_mode';

    /**
     * The mode as stored, falling back to `takeover`.
     *
     * ⚠️ The default is deliberately the PRE-EXISTING behaviour: before this
     * feature a live campaign banner took the hero outright. A store that has
     * never opened the new page therefore behaves exactly as it did, which is
     * what makes this safe to deploy without the client touching anything.
     */
    public static function mode(): string
    {
        $mode = (string) Setting::get(self::MODE_KEY, self::MODE_TAKEOVER);

        return in_array($mode, self::MODES, true) ? $mode : self::MODE_TAKEOVER;
    }

    /**
     * The live hero, in display order.
     *
     * @return list<array<string, mixed>>
     */
    public static function live(): array
    {
        $mode = self::mode();

        $campaign = $mode === self::MODE_MINE ? [] : self::campaignBanners();

        // Takeover: a running campaign owns the hero, and the client's slides
        // come back by themselves when it ends. Nothing to switch back.
        if ($mode === self::MODE_TAKEOVER && $campaign !== []) {
            return $campaign;
        }

        return [...$campaign, ...self::ownSlides()];
    }

    /**
     * The client's own slides.
     *
     * @return list<array<string, mixed>>
     */
    public static function ownSlides(): array
    {
        return HeroSlide::live()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HeroSlide $slide) => $slide->toHeroPayload())
            ->values()
            ->all();
    }

    /**
     * Banners belonging to a running store event.
     *
     * @return list<array<string, mixed>>
     */
    public static function campaignBanners(): array
    {
        return EventHeroBanner::live()
            // Ordered like the event strips (event sort, then start), then the
            // admin's order within the event. The join is only for ORDER BY.
            ->join('store_events', 'store_events.id', '=', 'event_hero_banners.store_event_id')
            ->orderBy('store_events.sort_order')
            ->orderBy('store_events.starts_at')
            ->orderBy('event_hero_banners.sort_order')
            ->orderBy('event_hero_banners.id')
            ->select('event_hero_banners.*')
            ->with(['product:id,slug,name_ar,name_en', 'event:id,name_ar,name_en'])
            ->get()
            ->map(fn (EventHeroBanner $b) => [
                'id' => 'event-'.$b->id,
                'kind' => HeroSlide::KIND_IMAGE,
                'image' => Media::url($b->image, 'hero'),
                // Null when there is no phone art: the hero then keeps the desktop
                // art on phones too (see hero.tsx — one shape for the whole set).
                'image_mobile' => $b->image_mobile ? Media::url($b->image_mobile, 'detail') : null,
                'video' => null,
                'href' => $b->product ? "/products/{$b->product->slug}" : "/shop?event={$b->store_event_id}",
                'alt_ar' => $b->alt_ar ?: ($b->product?->name_ar ?: $b->event->name_ar),
                'alt_en' => $b->alt_en ?: ($b->product?->name_en ?: $b->event->name_en),
            ])
            ->values()
            ->all();
    }
}
