<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ShippingCarrier;
use App\Models\User;
use App\Services\Shipping\CarrierDirectory;
use App\Services\Shipping\CarrierOption;
use App\Services\Shipping\Oto\OtoClient;
use App\Services\Shipping\Oto\OtoGateway;
use App\Services\Shipping\ShippingGateway;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

/**
 * The shipping carriers portal.
 *
 * 🔑 The tests that matter most are the first group: they pin that the enable
 * switch actually changes what gets shipped. A settings page whose toggle only
 * repaints a pill would pass every "does the page render" test ever written, so
 * the filtering is asserted through the real rate-quote path, not the UI.
 */
class ShippingCarriersTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://api.tryoto.com/rest/v2';

    protected function setUp(): void
    {
        parent::setUp();
        // The register's disabled set and OTO's listing are both cached; without
        // this, one test's carriers leak into the next.
        Cache::flush();
    }

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'a'.uniqid().'@test.com',
            'password' => bcrypt('x'), 'role' => 'admin',
        ]);
    }

    private function editor(array $permissions): User
    {
        return User::forceCreate([
            'name' => 'Editor', 'email' => 'e'.uniqid().'@test.com',
            'password' => bcrypt('x'), 'role' => 'editor',
            'permissions' => $permissions,
        ]);
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'RTB-CARRIER-'.uniqid(),
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Jeddah'],
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 100, 'shipping_fee' => 25, 'total' => 125,
        ]);
    }

    /** A real OtoGateway over a faked wire, so the filter is exercised for real. */
    private function gatewayOfferingRates(array $companies): OtoGateway
    {
        Http::fake([
            self::BASE.'/refreshToken' => Http::response(['access_token' => 'tok', 'expires_in' => '3600']),
            self::BASE.'/checkOTODeliveryFee' => Http::response(['deliveryCompany' => $companies]),
        ]);

        return new OtoGateway(new OtoClient('refresh', self::BASE), 'Riyadh', 'secret');
    }

    private function rate(int $id, string $company, float $price): array
    {
        return ['deliveryOptionId' => $id, 'deliveryCompanyName' => $company, 'price' => $price];
    }

    /** @return Mockery\MockInterface&ShippingGateway */
    private function fakeGateway(array $services = [], ?\Throwable $throws = null, ?array $pickup = null)
    {
        $gateway = Mockery::mock(ShippingGateway::class);

        if ($throws) {
            $gateway->shouldReceive('listServices')->andThrow($throws);
        } else {
            $gateway->shouldReceive('listServices')->andReturn($services);
        }

        // Stubbed explicitly rather than left to Mockery. safePickupLocations()
        // catches everything, so an unmocked call would be silently swallowed and
        // every assertion about the collection address would pass vacuously.
        $gateway->shouldReceive('pickupLocations')->andReturn($pickup ?? [
            ['name' => 'Retab Dates, Al Malqa', 'address' => 'King Fahd Branch Rd', 'city' => 'Riyadh', 'contact' => 'Saud'],
        ]);

        $this->app->instance(ShippingGateway::class, $gateway);

        return $gateway;
    }

    private function service(string $carrier, float $price = 20.0, ?string $name = null): CarrierOption
    {
        return new CarrierOption(id: 1, carrier: $carrier, service: $name, price: $price, estimatedDelivery: '1-2 days');
    }

    // ---------------------------------------------------------------------
    // The switch has to bite. Everything else on the page is decoration.
    // ---------------------------------------------------------------------

    /**
     * 🔴 The whole feature in one test: a carrier switched off in the panel must
     * disappear from the rate quote, which is the single path BOTH the manual
     * carrier picker and the automatic cheapest-carrier choice go through.
     */
    public function test_a_disabled_carrier_is_dropped_from_rate_quotes(): void
    {
        ShippingCarrier::create(['key' => 'naqel', 'name' => 'Naqel Express', 'is_enabled' => false]);

        $options = $this->gatewayOfferingRates([
            $this->rate(11, 'Naqel Express', 18.0),
            $this->rate(22, 'SMSA Express', 23.0),
        ])->getDeliveryOptions($this->order());

        $this->assertSame(['SMSA Express'], array_map(fn ($o) => $o->carrier, $options));
    }

    /**
     * The disabled carrier here is also the CHEAPEST, which is the case that
     * actually costs money: ShippingService picks the lowest price when the admin
     * leaves the picker on automatic, so a filter applied only in the UI would let
     * a banned courier be auto-selected with nobody choosing it.
     */
    public function test_a_disabled_carrier_cannot_win_the_automatic_cheapest_pick(): void
    {
        ShippingCarrier::create(['key' => 'naqel', 'name' => 'Naqel Express', 'is_enabled' => false]);

        $options = $this->gatewayOfferingRates([
            $this->rate(11, 'Naqel Express', 5.0),   // cheapest, and banned
            $this->rate(22, 'SMSA Express', 23.0),
        ])->getDeliveryOptions($this->order());

        usort($options, fn ($a, $b) => $a->price <=> $b->price);
        $this->assertSame('SMSA Express', $options[0]->carrier);
    }

    /**
     * 🔴 Regression for removing the hardcoded `stripos($carrier, 'aramex')` check.
     *
     * Both exclusions come from the migration's seeded rows now, and both must
     * survive OTO reporting a courier under a longer name. DHL is the interesting
     * half: the recorded decision always said "no Aramex, no DHL", but only Aramex
     * was ever implemented, so DHL could win the automatic pick.
     */
    public function test_the_seeded_exclusions_hold_across_name_drift(): void
    {
        $options = $this->gatewayOfferingRates([
            $this->rate(1, 'Aramex', 30.0),
            $this->rate(2, 'DHL Express', 40.0),
            $this->rate(3, 'SMSA', 23.0),
        ])->getDeliveryOptions($this->order());

        $this->assertSame(['SMSA'], array_map(fn ($o) => $o->carrier, $options));
    }

    /**
     * Fails OPEN on purpose. A courier nobody has registered yet is allowed,
     * because a surprise name on a label is recoverable while an order that cannot
     * be shipped at all is not.
     */
    public function test_an_unregistered_carrier_is_still_offered(): void
    {
        $options = $this->gatewayOfferingRates([
            $this->rate(9, 'Some New Courier', 15.0),
        ])->getDeliveryOptions($this->order());

        $this->assertSame(['Some New Courier'], array_map(fn ($o) => $o->carrier, $options));
    }

    /**
     * The register keys on a normalised name because OTO reports the same courier
     * differently across its endpoints. If this drifts, a disabled carrier quietly
     * becomes an unrecognised — and therefore allowed — one.
     */
    public function test_carrier_names_normalise_to_a_stable_key(): void
    {
        $cases = [
            'SMSA' => 'smsa',
            'SMSA Express' => 'smsa',
            'smsa  express' => 'smsa',
            'Naqel Express' => 'naqel',
            'DHL Express' => 'dhl',
            'J&T Express' => 'jt',
            'AyMakan' => 'aymakan',
            // Nothing but noise words: falls back to the stripped name rather than
            // collapsing to an empty key that would collide with every other one.
            'Express' => 'express',
        ];

        foreach ($cases as $name => $expected) {
            $this->assertSame($expected, ShippingCarrier::normalizeKey($name), "normalising [{$name}]");
        }
    }

    // ---------------------------------------------------------------------
    // Refreshing from OTO must never undo a decision the client made.
    // ---------------------------------------------------------------------

    /**
     * 🔴 The worst bug this feature could have. A refresh that re-enabled a
     * disabled carrier would present as the toggle "not saving", and the client
     * would keep shipping with a courier they had switched off.
     */
    public function test_a_refresh_never_re_enables_a_carrier_that_was_switched_off(): void
    {
        $carrier = ShippingCarrier::create(['key' => 'smsa', 'name' => 'SMSA Express', 'is_enabled' => false]);

        app(CarrierDirectory::class)->sync([$this->service('SMSA Express')->toArray()]);

        $this->assertFalse($carrier->fresh()->is_enabled);
    }

    /** Contact details are the client's, so a refresh only stamps availability. */
    public function test_a_refresh_never_overwrites_edited_contact_details(): void
    {
        $carrier = ShippingCarrier::create([
            'key' => 'smsa', 'name' => 'Our name for SMSA',
            'support_phone' => '+966 11 000 0000', 'website_url' => 'https://example.test',
        ]);

        app(CarrierDirectory::class)->sync([$this->service('SMSA Express')->toArray()]);

        $carrier->refresh();
        $this->assertSame('Our name for SMSA', $carrier->name);
        $this->assertSame('+966 11 000 0000', $carrier->support_phone);
        $this->assertSame('https://example.test', $carrier->website_url);
        $this->assertNotNull($carrier->last_seen_at, 'availability should still be stamped');
    }

    /** A carrier OTO starts offering appears by itself, with its seeded details. */
    public function test_a_newly_offered_carrier_is_registered_with_its_seed_details(): void
    {
        app(CarrierDirectory::class)->sync([$this->service('SMSA')->toArray()]);

        $carrier = ShippingCarrier::where('key', 'smsa')->firstOrFail();
        $this->assertTrue($carrier->is_enabled, 'a newly discovered carrier should be usable immediately');
        $this->assertSame('SMSA Express', $carrier->name, 'the seed name should win over the terser one OTO reported');
        $this->assertSame(CarrierDirectory::SEEDS['smsa']['website_url'], $carrier->website_url);
    }

    // ---------------------------------------------------------------------
    // Live data is never allowed to take the page down.
    // ---------------------------------------------------------------------

    /**
     * OTO being unreachable must surface as data, not an exception: the switches
     * and the support phone numbers are exactly what someone needs at that moment.
     */
    public function test_the_portal_still_renders_when_oto_is_unreachable(): void
    {
        $this->fakeGateway(throws: new \RuntimeException('OTO refreshToken failed: 401'));

        $this->actingAs($this->admin())
            ->get('/admin/shipping')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/shipping/index')
                ->where('error', 'OTO refreshToken failed: 401')
                // The seeded exclusions still list, so the page is usable.
                ->has('carriers', 2)
                ->where('carriers.0.available', false));
    }

    /** The rate-check fallback carries no per-service detail, and says so. */
    public function test_the_portal_flags_when_only_basic_rates_came_back(): void
    {
        $this->fakeGateway([$this->service('SMSA', 23.0)]);

        $this->actingAs($this->admin())
            ->get('/admin/shipping')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('detailed', false));
    }

    public function test_services_are_grouped_under_one_carrier_row(): void
    {
        $this->fakeGateway([
            $this->service('SPL', 18.0, 'Door to door'),
            $this->service('SPL', 12.0, 'PUDO'),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/shipping')
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $carriers = collect($page->toArray()['props']['carriers']);
                $spl = $carriers->firstWhere('key', 'spl');

                $this->assertNotNull($spl, 'SPL should be registered from the live listing');
                $this->assertCount(2, $spl['services'], 'both SPL services belong to one row');
                // assertEquals, not assertSame: PHP serialises a whole float as a
                // JSON int, so 12.0 arrives back as 12 (the same trap the money
                // assertions elsewhere in the suite hit).
                $this->assertEquals(12.0, $spl['cheapest']);
            });
    }

    // ---------------------------------------------------------------------
    // Guard rails on the switch itself.
    // ---------------------------------------------------------------------

    /**
     * 🔴 Switching off the last usable carrier would not fail here — it would fail
     * days later, when resolveOption() throws "no delivery options" on somebody
     * else's shipment, with nothing linking it back to this click.
     */
    public function test_the_last_available_carrier_cannot_be_switched_off(): void
    {
        $this->fakeGateway([$this->service('SMSA', 23.0)]);
        $admin = $this->admin();

        // Populates the register from the live listing.
        $this->actingAs($admin)->get('/admin/shipping')->assertOk();
        $smsa = ShippingCarrier::where('key', 'smsa')->firstOrFail();

        $this->actingAs($admin)
            ->patch("/admin/shipping/{$smsa->id}/toggle")
            ->assertRedirect()
            ->assertSessionHas('error', __('messages.admin.carrier_last_enabled'));

        $this->assertTrue($smsa->fresh()->is_enabled, 'the only usable carrier must stay on');
    }

    /** With a second carrier available, switching one off is allowed. */
    public function test_a_carrier_can_be_switched_off_when_another_remains(): void
    {
        $this->fakeGateway([$this->service('SMSA', 23.0), $this->service('Naqel', 18.0)]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/shipping')->assertOk();
        $smsa = ShippingCarrier::where('key', 'smsa')->firstOrFail();

        $this->actingAs($admin)
            ->patch("/admin/shipping/{$smsa->id}/toggle")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($smsa->fresh()->is_enabled);
    }

    public function test_contact_details_can_be_saved(): void
    {
        $carrier = ShippingCarrier::create(['key' => 'smsa', 'name' => 'SMSA Express']);

        $this->actingAs($this->admin())
            ->put("/admin/shipping/{$carrier->id}", [
                'name' => 'SMSA Express',
                'support_phone' => '+966 11 200 0000',
                'support_email' => 'care@smsa.test',
                'tracking_url' => 'https://smsa.test/track?n={tracking}',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $carrier->refresh();
        $this->assertSame('+966 11 200 0000', $carrier->support_phone);
        $this->assertSame(
            'https://smsa.test/track?n=ABC123',
            $carrier->trackingLink('ABC123'),
            'the {tracking} placeholder should build a real parcel link',
        );
    }

    // ---------------------------------------------------------------------
    // Where the courier collects. Read-only: the address lives in OTO.
    // ---------------------------------------------------------------------

    /**
     * The collection address is the one part of the shipping setup nothing in this
     * app can write, so the portal has to show what OTO reports and let a human
     * check it against the real shop.
     */
    public function test_the_portal_shows_where_couriers_collect(): void
    {
        $this->fakeGateway([$this->service('SMSA', 23.0)]);

        $this->actingAs($this->admin())
            ->get('/admin/shipping')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pickup', 1)
                ->where('pickup.0.name', 'Retab Dates, Al Malqa')
                ->where('pickup.0.city', 'Riyadh'));
    }

    /**
     * 🔴 A pickup-location failure must never cost a good carrier listing. It is a
     * separate OTO call folded into the same cached payload, so without the guard a
     * blip on the less important half would blank the more important one.
     */
    public function test_a_pickup_location_failure_does_not_lose_the_carrier_listing(): void
    {
        $gateway = Mockery::mock(ShippingGateway::class);
        $gateway->shouldReceive('listServices')->andReturn([$this->service('SMSA', 23.0)]);
        $gateway->shouldReceive('pickupLocations')->andThrow(new \RuntimeException('OTO getPickupLocationList failed: 500'));
        $this->app->instance(ShippingGateway::class, $gateway);

        $this->actingAs($this->admin())
            ->get('/admin/shipping')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('error', null)
                ->has('pickup', 0)
                // The carriers still arrived, which is the whole point.
                ->where('carriers.0.available', true));
    }

    // ---------------------------------------------------------------------
    // Permissions.
    // ---------------------------------------------------------------------

    /**
     * `view` is "see what it costs and who to phone"; `manage` changes what the
     * store ships with. An editor with the default grant gets the first and not
     * the second — the same split as settings.view / settings.edit.
     */
    public function test_an_editor_can_view_the_portal_but_not_change_it_without_manage(): void
    {
        $this->fakeGateway([$this->service('SMSA', 23.0)]);
        $carrier = ShippingCarrier::create(['key' => 'smsa', 'name' => 'SMSA Express']);

        $editor = $this->editor(Permission::preset('operations'));
        $this->assertTrue($editor->hasPermission('shipping.view'));

        $this->actingAs($editor)->get('/admin/shipping')->assertOk();

        $viewOnly = $this->editor(['shipping' => ['view' => true, 'manage' => false]]);
        $this->actingAs($viewOnly)->get('/admin/shipping')->assertOk();
        $this->actingAs($viewOnly)->patch("/admin/shipping/{$carrier->id}/toggle")->assertForbidden();
        $this->actingAs($viewOnly)->put("/admin/shipping/{$carrier->id}", ['name' => 'x'])->assertForbidden();
    }

    /**
     * An editor denied the section outright sees nothing, including the read-only
     * page. Pins that the route is gated, not just the sidebar entry.
     */
    public function test_an_editor_without_the_section_is_refused(): void
    {
        $editor = $this->editor(['shipping' => ['view' => false, 'manage' => false]]);

        $this->actingAs($editor)->get('/admin/shipping')->assertForbidden();
    }
}
