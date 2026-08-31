<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingCarrier;
use App\Services\Shipping\CarrierDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The shipping carriers portal (/admin/shipping).
 *
 * Answers the three questions staff actually have about a courier: can we ship
 * with it today and for how much (live from OTO), will we ship with it at all
 * (ours, the enable switch), and who do I call when a parcel goes missing (ours,
 * the contact card).
 *
 * 🔑 The enable switch is the only thing here that changes behaviour, and it is
 * not cosmetic: OtoGateway::getDeliveryOptions filters every rate quote through
 * ShippingCarrier::allows, so switching a carrier off removes it from the Ship
 * picker and from the automatic cheapest-carrier choice at the same time.
 *
 * ⚠️ Nothing on this page changes anything inside OTO. OTO can list and activate
 * carriers over its API (dcList / dcConfig / dcActivation) but exposes no
 * deactivation endpoint, and activation there means attaching the merchant's own
 * carrier contract with its credentials — a job for the OTO dashboard, not for
 * this panel. So "off" here means "Retab will not use it", never "OTO switched it
 * off", and the page says so rather than implying a reach it does not have.
 */
class ShippingController extends Controller
{
    /** Editable contact fields → validation. The allowlist; never accept the key. */
    private const FIELDS = [
        'name' => ['required', 'string', 'max:255'],
        'name_ar' => ['nullable', 'string', 'max:255'],
        'website_url' => ['nullable', 'url', 'max:255'],
        'support_phone' => ['nullable', 'string', 'max:32'],
        'support_email' => ['nullable', 'email', 'max:255'],
        'support_url' => ['nullable', 'url', 'max:255'],
        // Not `url`: it carries a {tracking} placeholder, and some carriers use it
        // inside the query string where a validator can reject the braces.
        'tracking_url' => ['nullable', 'string', 'max:255'],
        'oto_url' => ['nullable', 'url', 'max:255'],
        'notes' => ['nullable', 'string', 'max:2000'],
    ];

    public function index(Request $request, CarrierDirectory $directory)
    {
        $city = $request->query('city');

        return Inertia::render('admin/shipping/index', [
            ...$directory->overview(is_string($city) ? $city : null),
            'originCity' => (string) config('services.oto.origin_city', 'Riyadh'),
            // The dashboard the client already works in. Linked rather than
            // reproduced: anything that dispatches a courier belongs there.
            'otoUrl' => 'https://app.tryoto.com/shipments/pending-orders',
        ]);
    }

    /**
     * Re-ask OTO instead of serving the cached listing.
     *
     * A separate action rather than a query flag on index, so an ordinary page
     * load — or a back button — can never spend a live API call.
     */
    public function refresh(Request $request, CarrierDirectory $directory): RedirectResponse
    {
        $city = $request->query('city');
        $directory->overview(is_string($city) ? $city : null, refresh: true);

        return back()->with('success', __('messages.admin.carriers_refreshed'));
    }

    /**
     * Flip whether Retab will ship with this carrier.
     *
     * No confirm step: it is one click to undo, matching every other StatusToggle
     * in the panel. The guard that does matter is the last-one check below.
     */
    public function toggle(ShippingCarrier $carrier, CarrierDirectory $directory): RedirectResponse
    {
        // 🔴 Refuse to switch off the last available carrier. Every other carrier
        // being off means resolveOption() throws "no delivery options" on the next
        // shipment — so the failure would not surface here, it would surface days
        // later as an order that cannot be shipped, with nothing connecting it back
        // to this click. Only carriers OTO is actually offering count towards the
        // total; one that is enabled but unavailable cannot carry a parcel.
        if ($carrier->is_enabled && $this->lastAvailableCarrier($carrier, $directory)) {
            return back()->with('error', __('messages.admin.carrier_last_enabled'));
        }

        $carrier->update(['is_enabled' => ! $carrier->is_enabled]);

        return back()->with('success', __($carrier->is_enabled
            ? 'messages.admin.carrier_enabled'
            : 'messages.admin.carrier_disabled', ['name' => $carrier->name]));
    }

    public function update(Request $request, ShippingCarrier $carrier): RedirectResponse
    {
        $carrier->update($request->validate(self::FIELDS));

        return back()->with('success', __('messages.admin.carrier_saved', ['name' => $carrier->name]));
    }

    /**
     * Is this the only carrier left that could actually carry a parcel?
     *
     * Reads the cached listing, so it costs no API call. If OTO is unreachable the
     * listing is empty and this returns false — deliberately permissive: blocking
     * an admin from editing their carriers because a third party is down would be
     * the more annoying failure, and the shipment-time error still catches it.
     */
    private function lastAvailableCarrier(ShippingCarrier $carrier, CarrierDirectory $directory): bool
    {
        $available = collect($directory->live()['services'])
            ->map(fn (array $service) => ShippingCarrier::normalizeKey($service['carrier']))
            ->unique();

        if ($available->isEmpty()) {
            return false;
        }

        $disabled = ShippingCarrier::disabledKeys();

        $stillUsable = $available
            ->reject(fn (string $key) => isset($disabled[$key]) || $key === $carrier->key)
            ->count();

        return $stillUsable === 0 && $available->contains($carrier->key);
    }
}
