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

    /** @return array<string, array{bool|null, string|null, bool}> */
    public static function detectionCases(): array
    {
        return [
            // OTO's own flag is trusted outright when it says yes.
            'flag alone' => [true, 'Overnight', true],
            'flag false but name says PUDO' => [false, 'SMSA PUDO', true],

            'name: PUDO' => [null, 'SMSA PUDO', true],
            'name: hyphenated' => [null, 'SPL - PUDO', true],
            'name: lowercase' => [null, 'smsa pudo', true],
            'name: pickup point' => [null, 'Aramex Pickup Point', true],
            'name: locker' => [null, 'Naqel Locker', true],

            'plain door delivery' => [null, 'SMSA', false],
            'no flag, no name' => [null, null, false],
            // 🔴 The reason HINTS excludes "collect": this must NOT match, or an
            // ordinary door service would silently drop out of the automatic pick.
            'collect on delivery' => [null, 'Collect On Delivery', false],
            // Whole-phrase matching, never a bare substring.
            'a name merely containing the letters' => [null, 'Pudong Express', false],
        ];
    }

    #[DataProvider('detectionCases')]
    public function test_it_recognises_a_pickup_point(?bool $flag, ?string $name, bool $expected): void
    {
        $this->assertSame($expected, PickupPoint::detect($flag, $name));
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
     */
    public function test_the_rate_check_carries_the_service_name_and_the_pickup_flag(): void
    {
        $client = new class('refresh-token', 'https://api.example.test') extends OtoClient
        {
            public function checkDeliveryFee(array $payload): array
            {
                return ['deliveryCompany' => [
                    ['deliveryOptionId' => 1, 'deliveryCompanyName' => 'smsaV2', 'deliveryOptionName' => 'SMSA PUDO', 'price' => 13.92],
                    ['deliveryOptionId' => 2, 'deliveryCompanyName' => 'smsaV2', 'deliveryOptionName' => 'SMSA', 'price' => 24.36],
                ]];
            }
        };

        $options = (new OtoGateway($client, 'Riyadh', 'secret'))->getDeliveryOptions($this->order());

        $this->assertSame(['SMSA PUDO', 'SMSA'], array_map(fn ($o) => $o->service, $options));
        $this->assertTrue($options[0]->pickupDropoff, 'the PUDO row must be flagged');
        $this->assertFalse($options[1]->pickupDropoff, 'door delivery must not be');
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
