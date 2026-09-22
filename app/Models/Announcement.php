<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A message in the strip above the storefront.
 *
 * ⚠️ It ANNOUNCES; it does not enforce. "Stuffed dates ship to Riyadh only" tells
 * a shopper in Jeddah something, it does not stop them ordering — the admin
 * rejects that order at the confirm step. Client's explicit decision; enforcing
 * it would be a per-product shipping restriction, a separate piece of work.
 *
 * @mixin IdeHelperAnnouncement
 */
class Announcement extends Model
{
    use SoftDeletes;

    /** The looks an announcement can have, in the order the admin picker shows them. */
    public const TONES = ['info', 'warning', 'success'];

    protected $fillable = [
        'message_ar', 'message_en',
        'link_url', 'link_label_ar', 'link_label_en',
        'tone', 'is_active', 'starts_at', 'ends_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Showing on the storefront right now.
     *
     * 🔑 A NULL date means "no bound on that side", which is what makes the
     * client's "leave it up until I take it down" work without a second flag:
     * no `ends_at` simply never expires. Switching `is_active` off is the manual
     * take-down, and it is reversible, unlike deleting the row.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /**
     * What the admin list shows in its status pill.
     *
     * Deliberately mirrors scopeLive() rather than re-deciding: a row the list
     * calls "live" that the storefront does not show would read as a bug in the
     * storefront. `off` wins over the dates because it is the manual override.
     */
    public function state(): string
    {
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
}
