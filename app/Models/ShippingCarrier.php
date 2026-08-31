<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A shipping carrier the store knows about: whether Retab will ship with it, and
 * who to phone when a parcel goes missing.
 *
 * Availability, prices and delivery times are NOT here — they are read live from
 * OTO (see CarrierDirectory). This row is only the half OTO cannot tell us.
 *
 * @mixin IdeHelperShippingCarrier
 */
class ShippingCarrier extends Model
{
    /** Cached set of keys the store has switched OFF, read on every rate quote. */
    public const DISABLED_CACHE_KEY = 'shipping_carriers_disabled';

    /**
     * Noise words dropped when building a match key.
     *
     * OTO names the same courier differently across its endpoints — the rate quote
     * says "SMSA Express" where the carrier list says "SMSA" — so keying on the raw
     * name would let a disabled carrier quietly come back as an unrecognised, and
     * therefore allowed, one.
     *
     * ⚠️ Keep this list TIGHT. Every word added risks collapsing two genuinely
     * different couriers onto one key, which would switch off the wrong one. It is
     * safe for the carriers OTO actually offers in the Gulf (SMSA, Naqel, SPL,
     * iMile, AyMakan, J&T, Aramex, DHL, Zajil, Kwick) — check any addition against
     * that list before extending it.
     */
    private const NOISE = ['express', 'expres', 'logistics', 'logistic', 'shipping', 'delivery', 'courier', 'company', 'co', 'ltd', 'llc', 'sa', 'ksa'];

    protected $fillable = [
        'key',
        'name',
        'name_ar',
        'is_enabled',
        'website_url',
        'support_phone',
        'support_email',
        'support_url',
        'tracking_url',
        'oto_url',
        'notes',
        'sort_order',
        'last_seen_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The quote path reads the disabled set on every shipment, so it is cached;
        // any write here has to drop it or a carrier switched off in the panel would
        // keep being offered until the TTL lapsed.
        static::saved(fn () => Cache::forget(self::DISABLED_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::DISABLED_CACHE_KEY));
    }

    /**
     * Reduce a carrier name to a stable match key: lowercase, accents and
     * punctuation stripped, noise words removed.
     *
     *   "SMSA Express"  → smsa
     *   "Naqel Express" → naqel
     *   "SPL - PUDO"    → splpudo
     *
     * Falls back to the stripped name when removing noise would leave nothing, so a
     * courier genuinely called "Express" still gets a key instead of an empty one
     * that would collide with every other such case.
     */
    public static function normalizeKey(string $name): string
    {
        $clean = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $name) ?? '');
        $words = array_values(array_filter(explode(' ', $clean), fn ($w) => $w !== ''));

        $kept = array_values(array_filter($words, fn ($w) => ! in_array($w, self::NOISE, true)));

        return implode('', $kept !== [] ? $kept : $words);
    }

    /**
     * Keys the store has switched off, as a lookup set.
     *
     * 🔴 This is what makes the enable toggle real rather than cosmetic: it is read
     * by OtoGateway::getDeliveryOptions, so a disabled carrier disappears from the
     * Ship picker AND can never be chosen as the automatic cheapest option.
     *
     * Cached because it is hit on every rate lookup, and busted on every write to
     * this table (see booted()). The one-hour TTL is only a safety net.
     *
     * ⚠️ Fails OPEN by design, twice over: an unknown carrier is not in this set so
     * it is allowed, and a cache or database failure yields an empty set rather than
     * an exception. A surprise courier on a label is recoverable; an order that
     * cannot be shipped at all is not.
     *
     * @return array<string, true>
     */
    public static function disabledKeys(): array
    {
        try {
            return Cache::remember(
                self::DISABLED_CACHE_KEY,
                3600,
                fn () => static::query()->where('is_enabled', false)->pluck('key')->flip()->map(fn () => true)->all(),
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** Whether the store will ship with the carrier OTO reported under this name. */
    public static function allows(string $carrierName): bool
    {
        return ! isset(static::disabledKeys()[static::normalizeKey($carrierName)]);
    }

    /** Public tracking link for a parcel, or null when no template is on file. */
    public function trackingLink(?string $trackingNumber): ?string
    {
        if (! $this->tracking_url || ! $trackingNumber) {
            return null;
        }

        return str_replace('{tracking}', rawurlencode($trackingNumber), $this->tracking_url);
    }
}
