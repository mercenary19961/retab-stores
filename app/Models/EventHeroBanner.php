<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One homepage hero banner belonging to a store event. See the
 * create_event_hero_banners migration for the model.
 *
 * @mixin IdeHelperEventHeroBanner
 */
class EventHeroBanner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_event_id',
        'image',
        'image_mobile',
        'product_id',
        'alt_ar',
        'alt_en',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(StoreEvent::class, 'store_event_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Banners on the storefront right now.
     *
     * Switched on, its event running, inside its own optional window, and — when
     * it links to an offer — that offer still live. 🔑 The last condition is what
     * stops a banner advertising a product that has sold out of its window or been
     * hidden: it would otherwise send every click to a 404.
     *
     * Columns are qualified because the homepage query joins `store_events` (for
     * ordering), which has its own `is_active`, `starts_at` and `ends_at`.
     */
    public function scopeLive(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->where($query->qualifyColumn('is_active'), true)
            ->whereHas('event', fn (Builder $e) => $e->running())
            ->where(fn (Builder $w) => $w->whereNull($query->qualifyColumn('starts_at'))->orWhere($query->qualifyColumn('starts_at'), '<=', $now))
            ->where(fn (Builder $w) => $w->whereNull($query->qualifyColumn('ends_at'))->orWhere($query->qualifyColumn('ends_at'), '>=', $now))
            // Linked to an offer → that offer must be live. Linked to nothing → the
            // banner opens its event's own page, so the EVENT must have a live
            // offer, or the click lands on "0 products". Found on production: a
            // hand-made duplicate event with no offers had a banner scheduled.
            ->where(fn (Builder $w) => $w
                ->where(fn (Builder $x) => $x->whereNull($query->qualifyColumn('product_id'))
                    ->whereHas('event.products', fn (Builder $p) => $p->where('is_active', true)))
                ->orWhereHas('product', fn (Builder $p) => $p->where('is_active', true)));
    }

    /**
     * Why a banner is or is not showing, for the admin: live / scheduled / ended /
     * off / event_paused / offer_hidden / no_offers. Mirrors scopeLive() so the panel never
     * calls a banner live that the storefront is not showing.
     *
     * ⚠️ Reads `event` and `product`; the caller should have both loaded.
     */
    public function state(): string
    {
        if (! $this->is_active) {
            return 'off';
        }
        if (! $this->event->is_active) {
            return 'event_paused';
        }
        if ($this->product_id !== null && (! $this->product || ! $this->product->is_active)) {
            return 'offer_hidden';
        }
        if ($this->product_id === null && ! $this->event->products()->where('is_active', true)->exists()) {
            return 'no_offers';
        }

        $start = $this->starts_at ?? $this->event->starts_at;
        $end = $this->ends_at ?? $this->event->ends_at;

        // A banner's own window can only narrow its event's, never extend it.
        if ($start->isFuture() || $this->event->starts_at->isFuture()) {
            return 'scheduled';
        }
        if ($end->isPast() || $this->event->ends_at->isPast()) {
            return 'ended';
        }

        return 'live';
    }
}
