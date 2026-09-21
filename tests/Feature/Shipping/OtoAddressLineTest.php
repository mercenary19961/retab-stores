<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Shipping\Oto\OtoClient;
use App\Services\Shipping\Oto\OtoGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What the courier actually reads off the label.
 *
 * 🔴 Until 2026-09-21 `pushOrder()` sent `building + street` and nothing else, so
 * the national address short code — the one field Saudi couriers navigate by,
 * which we ask for at checkout and store on both the order and the account —
 * never left our database.
 */
class OtoAddressLineTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string,mixed>  $address */
    private function pushWith(array $address): array
    {
        config([
            'services.oto.refresh_token' => 'test-token',
            'services.oto.base_url' => 'https://api.tryoto.test',
        ]);

        Http::fake([
            '*refreshToken*' => Http::response(['access_token' => 'tok', 'expires_in' => '3600']),
            '*createOrder*' => Http::response(['otoId' => 'OTO-1']),
        ]);

        $order = Order::forceCreate([
            'order_number' => 'RTB-TEST-'.uniqid(),
            'customer_name' => 'Zaid',
            'customer_phone' => '0512345678',
            'shipping_address' => $address,
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 100,
            'shipping_fee' => 25,
            'total' => 125,
        ]);

        $gateway = new OtoGateway(
            client: app(OtoClient::class),
            originCity: 'Riyadh',
            webhookSecret: 'secret',
        );
        $gateway->pushOrder($order);

        $sent = [];
        Http::assertSent(function ($request) use (&$sent) {
            if (str_contains($request->url(), 'createOrder')) {
                $sent = $request->data();
            }

            return true;
        });

        return $sent['customer'] ?? [];
    }

    /** 🔴 The regression: the short code has to reach the label. */
    public function test_the_national_address_is_sent_to_the_courier(): void
    {
        $customer = $this->pushWith([
            'building' => '4002',
            'street' => 'King Fahd Road',
            'district' => 'Al Malqa',
            'short_address' => 'RRMD7708',
            'city' => 'Riyadh',
            'country' => 'SA',
        ]);

        $this->assertStringContainsString('RRMD7708', $customer['address']);
        $this->assertSame('4002 King Fahd Road, Al Malqa, RRMD7708', $customer['address']);
    }

    /** Empty parts are dropped, so a sparse address is not a row of commas. */
    public function test_missing_parts_do_not_leave_stray_separators(): void
    {
        $customer = $this->pushWith([
            'street' => 'King Fahd Road',
            'short_address' => 'RRMD7708',
            'city' => 'Riyadh',
        ]);

        $this->assertSame('King Fahd Road, RRMD7708', $customer['address']);
    }

    /** OTO wants something rather than an empty string. */
    public function test_an_empty_address_falls_back_rather_than_sending_nothing(): void
    {
        $customer = $this->pushWith(['city' => 'Riyadh', 'country' => 'SA']);

        $this->assertSame('N/A', $customer['address']);
    }
}
