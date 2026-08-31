<?php

namespace App\Services\Shipping;

use App\Models\ShippingCarrier;
use Illuminate\Support\Facades\Cache;

/**
 * Joins the two halves of the shipping portal: what OTO can offer right now
 * (live, never stored) and what the store has decided about each carrier
 * (stored, never guessed).
 *
 * It also keeps the register honest — a carrier OTO starts offering is added on
 * the next refresh rather than needing a deploy, and one it stops offering keeps
 * its row (and the client's own contact notes) but is shown as unavailable.
 */
class CarrierDirectory
{
    /** How long a live listing is reused before OTO is asked again. */
    private const TTL = 600;

    private const CACHE_PREFIX = 'shipping_carriers_live:';

    /**
     * Starting details for carriers we can recognise, applied ONCE when a carrier
     * is first discovered. Never re-applied, so an admin's correction is permanent.
     *
     * ⚠️ These are public best-effort defaults, NOT verified against the store's own
     * OTO contracts — they exist so the portal is useful on day one instead of a
     * grid of blanks. Every field is editable in the panel, and the client should
     * check them once. Support phone and email are deliberately absent: a wrong
     * website is obvious the moment someone clicks it, whereas a wrong support
     * number sends staff to a stranger, so those are the client's to fill in from
     * their own account manager.
     *
     * `{tracking}` is replaced with the parcel's tracking number.
     */
    public const SEEDS = [
        'smsa' => [
            'name' => 'SMSA Express',
            'name_ar' => 'سمسا',
            'website_url' => 'https://www.smsaexpress.com',
            'tracking_url' => 'https://www.smsaexpress.com/track?tracknumbers={tracking}',
            'sort_order' => 10,
        ],
        'naqel' => [
            'name' => 'Naqel Express',
            'name_ar' => 'ناقل',
            'website_url' => 'https://www.naqelexpress.com',
            'sort_order' => 20,
        ],
        'spl' => [
            'name' => 'Saudi Post (SPL)',
            'name_ar' => 'البريد السعودي',
            'website_url' => 'https://splonline.com.sa',
            'sort_order' => 30,
        ],
        'imile' => [
            'name' => 'iMile',
            'name_ar' => 'آي مايل',
            'website_url' => 'https://www.imile.com',
            'sort_order' => 40,
        ],
        'aymakan' => [
            'name' => 'AyMakan',
            'name_ar' => 'أي مكان',
            'website_url' => 'https://aymakan.com.sa',
            'sort_order' => 50,
        ],
        'jt' => [
            'name' => 'J&T Express',
            'name_ar' => 'جي آند تي',
            'website_url' => 'https://www.jtexpress.sa',
            'sort_order' => 60,
        ],
        'zajil' => [
            'name' => 'Zajil Express',
            'name_ar' => 'زاجل',
            'website_url' => 'https://www.zajil-express.com',
            'sort_order' => 70,
        ],
    ];

    public function __construct(
        protected ShippingGateway $gateway,
    ) {}

    /**
     * The whole portal payload: every carrier the store knows about, merged with
     * whatever OTO is offering today.
     *
     * @return array<string, mixed>
     */
    public function overview(?string $city = null, bool $refresh = false): array
    {
        $live = $this->live($city, $refresh);

        // Group the live services under their carrier's match key, so a courier
        // offering three services (SPL door delivery, SPL PUDO, ...) reads as one
        // row with three services rather than three unrelated rows the admin would
        // have to switch on and off separately.
        $byKey = [];
        foreach ($live['services'] as $service) {
            $byKey[ShippingCarrier::normalizeKey($service['carrier'])][] = $service;
        }

        $carriers = ShippingCarrier::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (ShippingCarrier $carrier) use ($byKey) {
                $services = $byKey[$carrier->key] ?? [];
                $prices = array_values(array_filter(array_column($services, 'price'), fn ($p) => $p !== null));

                return [
                    'id' => $carrier->id,
                    'key' => $carrier->key,
                    'name' => $carrier->name,
                    'name_ar' => $carrier->name_ar,
                    'is_enabled' => $carrier->is_enabled,
                    'website_url' => $carrier->website_url,
                    'support_phone' => $carrier->support_phone,
                    'support_email' => $carrier->support_email,
                    'support_url' => $carrier->support_url,
                    'tracking_url' => $carrier->tracking_url,
                    'oto_url' => $carrier->oto_url,
                    'notes' => $carrier->notes,
                    'last_seen_at' => $carrier->last_seen_at?->toIso8601String(),
                    // Availability is about OTO, enablement is about us. Kept apart
                    // on purpose: "OTO is not offering this" and "we switched this
                    // off" need completely different responses from the reader.
                    'available' => $services !== [],
                    'services' => $services,
                    'cheapest' => $prices !== [] ? min($prices) : null,
                    'currency' => $services[0]['currency'] ?? 'SAR',
                ];
            })
            ->values()
            ->all();

        return [
            'carriers' => $carriers,
            'city' => $live['city'],
            'error' => $live['error'],
            'fetched_at' => $live['fetched_at'],
            // Where couriers collect. Read-only by choice, not by necessity: OTO
            // has update endpoints we deliberately do not call (see OtoClient).
            'pickup' => $live['pickup'] ?? [],
            // True when OTO answered with the full catalogue rather than the plain
            // rate check. Derived from the data itself rather than claimed, so it
            // cannot drift from what is actually on screen.
            'detailed' => (bool) array_filter(
                $live['services'],
                fn ($s) => $s['service'] !== null || $s['logo'] !== null || $s['pickup_cut_off'] !== null,
            ),
        ];
    }

    /**
     * OTO live list, cached briefly and never allowed to throw.
     *
     * 🔑 A failure here has to be DATA, not an exception: the other job of this page
     * is showing the enable switches and the support contacts, and those still work
     * perfectly when OTO is unreachable. Throwing would take the whole portal down
     * over a network blip, exactly when someone is most likely trying to phone a
     * courier.
     *
     * @return array{services: list<array<string, mixed>>, error: string|null, city: string|null, fetched_at: string|null, pickup: list<array<string, mixed>>}
     */
    public function live(?string $city = null, bool $refresh = false): array
    {
        $city = $city !== null && trim($city) !== '' ? trim($city) : null;
        $key = self::CACHE_PREFIX.md5(mb_strtolower($city ?? ''));

        if ($refresh) {
            Cache::forget($key);
        }

        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        try {
            $services = array_map(
                fn (CarrierOption $option) => $option->toArray(),
                $this->gateway->listServices($city),
            );
            $payload = [
                'services' => $services,
                'error' => null,
                'city' => $city,
                'fetched_at' => now()->toIso8601String(),
                // Best-effort and cached alongside the rates, so showing the
                // collection address costs no extra round trip. A failure here must
                // not lose a good carrier listing.
                'pickup' => $this->safePickupLocations(),
            ];

            // Only on a real fetch — this is what keeps the write out of the common
            // cached page load, so browsing the portal costs no database writes.
            $this->sync($services);
        } catch (\Throwable $e) {
            report($e);
            $payload = ['services' => [], 'error' => $e->getMessage(), 'city' => $city, 'fetched_at' => null, 'pickup' => []];
        }

        // Cached either way, including the failure: without that, a page left open
        // would retry a dead credential on every load. The failure is cached for far
        // less time so a fixed credential is picked up quickly.
        Cache::put($key, $payload, $payload['error'] === null ? self::TTL : 60);

        return $payload;
    }

    /** Never lets a pickup-location failure cost us a good carrier listing. */
    private function safePickupLocations(): array
    {
        try {
            return $this->gateway->pickupLocations();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Record what OTO just offered: add carriers we have not met, and stamp the
     * ones we have as still available.
     *
     * ⚠️ Only ever CREATES rows and touches `last_seen_at`. It must never write
     * `is_enabled` or any contact field on an existing row — a refresh silently
     * re-enabling a carrier the client had switched off would be the worst bug this
     * feature could have, and it would present as the toggle simply not saving.
     *
     * @param  list<array<string, mixed>>  $services
     */
    public function sync(array $services): void
    {
        $seen = [];
        foreach ($services as $service) {
            $key = ShippingCarrier::normalizeKey($service['carrier']);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = $service['carrier'];
        }

        if ($seen === []) {
            return;
        }

        $existing = ShippingCarrier::whereIn('key', array_keys($seen))->get()->keyBy('key');

        foreach ($seen as $key => $reportedName) {
            $carrier = $existing->get($key);

            if ($carrier) {
                $carrier->update(['last_seen_at' => now()]);

                continue;
            }

            $seed = self::SEEDS[$key] ?? [];
            ShippingCarrier::create([
                'key' => $key,
                // Prefer our tidier name where we have one; OTO naming is
                // inconsistent across endpoints ("SMSA" on one, "SMSA Express" on
                // another) and the register is what the client reads.
                'name' => $seed['name'] ?? $reportedName,
                'name_ar' => $seed['name_ar'] ?? null,
                // New carriers arrive ON. See ShippingCarrier::disabledKeys for why
                // this side of the default is the safe one.
                'is_enabled' => true,
                'website_url' => $seed['website_url'] ?? null,
                'tracking_url' => $seed['tracking_url'] ?? null,
                'sort_order' => $seed['sort_order'] ?? 80,
                'last_seen_at' => now(),
            ]);
        }
    }
}
