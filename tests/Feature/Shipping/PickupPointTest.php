<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Shipping\DeliveryOption;
use App\Services\Shipping\Oto\OtoClient;
use App\Services\Shipping\Oto\OtoGateway;
use App\Services\Shipping\PickupPoint;
use App\Services\Shipping\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pickup-point ("PUDO") services: recognising them, and keeping them off the
 * automatic carrier pick.
 *
 * 🔴 The bug these pin: SMSA quotes two services under ONE company name —
 * "SMSA PUDO" at 13.92 and "SMSA" at 24.36 SAR. The quote path carried only the
 * company name and the automatic pick took the cheapest, so a customer who paid
 * Retab's flat home-delivery fee would have been sent to collect their own
 * parcel from a branch, with nothing in the panel saying so.
 */
class PickupPointTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- predicate

    /** @return array<string, array{string|null, string|null, bool}> */
    public static function detectionCases(): array
    {
        return [
            // OTO's deliveryType is trusted outright when it says the customer
            // collects. Values taken from a live rate-check response.
            'deliveryType: pickupByCustomer' => ['pickupByCustomer', 'Overnight', true],
            'deliveryType: locker' => ['locker', 'Redbox', true],
            'deliveryType in another style' => ['pickup_by_customer', 'Anything', true],

            // 🔴 The regression. Every one of these is a REAL `pickupDropoff`
            // value, and the old code cast that field to bool — so all four came
            // out true and every carrier was a pickup point. They describe the
            // first mile (who hands the parcel over at OUR end) and must have no
            // bearing whatsoever on whether the CUSTOMER collects.
            'door delivery, courier collects from us' => ['toCustomerDoorstep', 'Naqel Express', false],
            'door delivery, we drop off at the branch' => ['toCustomerDoorstep', 'SMSA', false],
            'door delivery, free pickup and dropoff' => ['toCustomerDoorstep', 'Aramex', false],
            'door delivery, cold chain' => ['toCustomerDoorstep', 'Adwar Cold', false],

            // An unrecognised type falls through to the name rather than being
            // assumed to be door delivery — the safe direction.
            'unknown type but name says PUDO' => ['somethingNew', 'SMSA PUDO', true],

            'name: PUDO' => [null, 'SMSA PUDO', true],
            'name: hyphenated' => [null, 'SPL - PUDO', true],
            'name: lowercase' => [null, 'smsa pudo', true],
            'name: pickup point' => [null, 'Aramex Pickup Point', true],
            'name: locker' => [null, 'Naqel Locker', true],

            'plain door delivery' => [null, 'SMSA', false],
            'nothing to go on' => [null, null, false],
            // 🔴 The reason HINTS excludes "collect": this must NOT match, or an
            // ordinary door service would silently drop out of the automatic pick.
            'collect on delivery' => [null, 'Collect On Delivery', false],
            // Whole-phrase matching, never a bare substring.
            'a name merely containing the letters' => [null, 'Pudong Express', false],
        ];
    }

    #[DataProvider('detectionCases')]
    public function test_it_recognises_a_pickup_point(?string $deliveryType, ?string $name, bool $expected): void
    {
        $this->assertSame($expected, PickupPoint::detect($deliveryType, $name));
    }

    // ------------------------------------------------------------ the auto pick

    private function option(int $id, float $price, bool $pickup = false): DeliveryOption
    {
        return new DeliveryOption(
            id: $id,
            carrier: 'smsaV2',
            price: $price,
            service: $pickup ? 'SMSA PUDO' : 'SMSA',
            pickupDropoff: $pickup,
        );
    }

    /**
     * 🔴 The regression. Proven to fail against the old `$options[0]`: it
     * returned 1 (the 13.92 pickup point) instead of 2.
     */
    public function test_the_automatic_pick_skips_a_pickup_point_even_when_it_is_cheapest(): void
    {
        $chosen = ShippingService::preferredOption([
            $this->option(1, 13.92, pickup: true),
            $this->option(2, 24.36),
        ]);

        $this->assertSame(2, $chosen?->id, 'automatic must ship to the door, not to a collection point');
    }

    /** With no pickup point in the way, it is still simply the cheapest. */
    public function test_the_automatic_pick_is_otherwise_the_cheapest(): void
    {
        $chosen = ShippingService::preferredOption([
            $this->option(1, 24.36),
            $this->option(2, 19.50),
        ]);

        $this->assertSame(2, $chosen?->id);
    }

    /**
     * Fails OPEN. An order that cannot ship at all is a worse outcome than one
     * shipped to a collection point, and the failure would surface days later on
     * somebody else's shipment as "no delivery options".
     */
    public function test_it_falls_back_to_the_cheapest_when_every_option_is_a_pickup_point(): void
    {
        $chosen = ShippingService::preferredOption([
            $this->option(1, 24.36, pickup: true),
            $this->option(2, 13.92, pickup: true),
        ]);

        $this->assertSame(2, $chosen?->id);
    }

    public function test_it_returns_null_when_there_is_nothing_to_choose(): void
    {
        $this->assertNull(ShippingService::preferredOption([]));
    }

    // -------------------------------------------------------- gateway → the DTO

    /**
     * The whole chain on the path that actually ships an order: OTO's rate-check
     * payload → DeliveryOption. The service name used to be dropped here, which
     * is what made the two SMSA rows indistinguishable in the Ship dialog.
     *
     * 🔴 THE ROWS BELOW ARE COPIED FROM A LIVE RESPONSE, fields and all, and that
     * is the point. The old fixture carried only id / company / name / price — no
     * `pickupDropoff`, no `deliveryType` — so the detection ran on the service
     * NAME alone and passed, while the real payload (where `pickupDropoff` is a
     * truthy string on every row) flagged all four of these as pickup points. A
     * fixture thinner than the real thing is how a bug hides behind a green test.
     */
    public function test_the_rate_check_carries_the_service_name_and_the_pickup_flag(): void
    {
        $client = new class('refresh-token', 'https://api.example.test') extends OtoClient
        {
            public function checkDeliveryFee(array $payload): array
            {
                return ['deliveryCompany' => [
                    ['deliveryOptionId' => 7323, 'deliveryCompanyName' => 'smsaV2', 'deliveryOptionName' => 'SMSA PUDO', 'price' => 13.92,
                        'deliveryType' => 'pickupByCustomer', 'pickupDropoff' => 'dropoffOnly'],
                    ['deliveryOptionId' => 7175, 'deliveryCompanyName' => 'redboxv2', 'deliveryOptionName' => 'Redbox', 'price' => 14.0,
                        'deliveryType' => 'locker', 'pickupDropoff' => 'lockerDropOff'],
                    ['deliveryOptionId' => 5441, 'deliveryCompanyName' => 'naqel', 'deliveryOptionName' => 'Naqel Express', 'price' => 23.1,
                        'deliveryType' => 'toCustomerDoorstep', 'pickupDropoff' => 'freePickup'],
                    ['deliveryOptionId' => 9804, 'deliveryCompanyName' => 'smsaV2', 'deliveryOptionName' => 'SMSA', 'price' => 24.36,
                        'deliveryType' => 'toCustomerDoorstep', 'pickupDropoff' => 'dropoffOnly'],
                ]];
            }
        };

        $options = (new OtoGateway($client, 'Riyadh', 'secret'))->getDeliveryOptions($this->order());

        $this->assertSame(['SMSA PUDO', 'Redbox', 'Naqel Express', 'SMSA'], array_map(fn ($o) => $o->service, $options));
        $this->assertTrue($options[0]->pickupDropoff, 'a counter collection must be flagged');
        // 🔴 Redbox is the case the NAME cannot catch: a locker whose service name
        // carries no hint word. Only deliveryType says so.
        $this->assertTrue($options[1]->pickupDropoff, 'a locker must be flagged even when its name says nothing');
        $this->assertFalse($options[2]->pickupDropoff, 'freePickup is about OUR end, not the customer collecting');
        $this->assertFalse($options[3]->pickupDropoff, 'dropoffOnly is about OUR end too');
    }

    /**
     * 🔴 The money test, on the real shape: the cheapest two rows are a counter and
     * a locker, and automatic must skip BOTH and choose the cheapest door
     * delivery. Against the old `(bool)` cast every row was a pickup point, so
     * preferredOption() found no door delivery, fell through to its
     * cheapest-overall fallback and returned the 13.92 SMSA counter — sending a
     * customer who paid the flat home-delivery fee out to fetch their own parcel.
     */
    public function test_the_automatic_pick_chooses_door_delivery_from_a_real_payload(): void
    {
        $client = new class('refresh-token', 'https://api.example.test') extends OtoClient
        {
            public function checkDeliveryFee(array $payload): array
            {
                return ['deliveryCompany' => [
                    ['deliveryOptionId' => 7323, 'deliveryCompanyName' => 'smsaV2', 'deliveryOptionName' => 'SMSA PUDO', 'price' => 13.92,
                        'deliveryType' => 'pickupByCustomer', 'pickupDropoff' => 'dropoffOnly'],
                    ['deliveryOptionId' => 7175, 'deliveryCompanyName' => 'redboxv2', 'deliveryOptionName' => 'Redbox', 'price' => 14.0,
                        'deliveryType' => 'locker', 'pickupDropoff' => 'lockerDropOff'],
                    ['deliveryOptionId' => 5442, 'deliveryCompanyName' => 'delexLogestechs', 'deliveryOptionName' => 'Delex', 'price' => 15.99,
                        'deliveryType' => 'toCustomerDoorstep', 'pickupDropoff' => 'freePickup'],
                ]];
            }
        };

        $chosen = ShippingService::preferredOption(
            (new OtoGateway($client, 'Riyadh', 'secret'))->getDeliveryOptions($this->order())
        );

        $this->assertSame(5442, $chosen?->id, 'automatic must skip the counter AND the locker');
        $this->assertSame(15.99, $chosen?->price);
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'RTB-PUDO-1',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA', 'city' => 'Jeddah'],
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 100,
            'shipping_fee' => 25,
            'total' => 125,
        ]);
    }
}
