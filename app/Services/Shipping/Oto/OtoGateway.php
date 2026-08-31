<?php

namespace App\Services\Shipping\Oto;

use App\Models\Order;
use App\Models\ShippingCarrier;
use App\Services\Shipping\CarrierOption;
use App\Services\Shipping\DeliveryOption;
use App\Services\Shipping\NormalizedShipment;
use App\Services\Shipping\ShippingGateway;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * OTO (Tryoto) implementation of the ShippingGateway contract. Maps Retab orders
 * into OTO's payloads and normalizes its responses back into provider-agnostic
 * DTOs. Retab is prepaid-only (no COD). Field extraction is defensive because
 * OTO's response keys vary by carrier/endpoint.
 */
class OtoGateway implements ShippingGateway
{
    public function __construct(
        protected OtoClient $client,
        protected string $originCity,
        protected string $webhookSecret,
    ) {}

    public function pushOrder(Order $order): int
    {
        $order->loadMissing('items');
        $address = is_array($order->shipping_address) ? $order->shipping_address : [];

        $data = $this->client->createOrder([
            'orderId' => $order->order_number,
            'payment_method' => 'paid',   // Retab is prepaid only — never COD
            'amount' => (float) $order->total,
            'amount_due' => 0,
            'currency' => 'SAR',
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'mobile' => $order->customer_phone,
                'address' => trim(($address['building'] ?? '').' '.($address['street'] ?? '')) ?: ($address['district'] ?? 'N/A'),
                'city' => $address['city'] ?? $this->originCity,
                'country' => $address['country'] ?? 'SA',
            ],
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name_ar,
                'sku' => $item->smacc_sku ?: $item->sku,
                'price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
            ])->values()->all(),
        ]);

        $otoId = $data['otoId'] ?? null;
        if (! $otoId) {
            throw new RuntimeException('OTO createOrder did not return an otoId.');
        }

        return (int) $otoId;
    }

    public function getDeliveryOptions(Order $order): array
    {
        $address = is_array($order->shipping_address) ? $order->shipping_address : [];

        $data = $this->client->checkDeliveryFee([
            'originCity' => $this->originCity,
            'destinationCity' => $address['city'] ?? $this->originCity,
            'weight' => 1,
            'codAmount' => 0, // prepaid only
            'currency' => 'SAR',
        ]);

        $rows = $data['deliveryCompany'] ?? $data['data'] ?? $data['options'] ?? (array_is_list($data) ? $data : []);

        $options = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = $row['deliveryOptionId'] ?? $row['id'] ?? null;
            if ($id === null) {
                continue;
            }
            $carrier = $row['deliveryCompanyName'] ?? $row['name'] ?? 'Carrier';

            // Which couriers the store will ship with is a business decision the
            // client now owns, at /admin/shipping.
            //
            // 🔑 This replaces a hardcoded `stripos($carrier, 'aramex')`. That was
            // only half the recorded decision — "no Aramex, no DHL" — so DHL was
            // being quoted and could win the automatic cheapest-carrier pick, and
            // neither exclusion could be changed without a deploy.
            //
            // Filtering HERE rather than in the picker is deliberate: this is the
            // one path both the manual picker and the automatic choice go through
            // (ShippingService::resolveOption re-quotes even for a manual pick), so
            // a disabled carrier cannot be reached by either route.
            if (! ShippingCarrier::allows($carrier)) {
                continue;
            }

            $options[] = new DeliveryOption(
                id: (int) $id,
                carrier: $carrier,
                price: (float) ($row['price'] ?? $row['shippingFee'] ?? $row['totalCharge'] ?? 0),
                currency: $row['currency'] ?? 'SAR',
                estimatedDelivery: $row['deliveryTime'] ?? $row['estimatedDeliveryTime'] ?? null,
                raw: $row,
            );
        }

        return $options;
    }

    public function createShipment(Order $order, int $deliveryOptionId): NormalizedShipment
    {
        $data = $this->client->createShipment([
            'orderId' => $order->order_number,
            'deliveryOptionId' => $deliveryOptionId,
        ]);

        $tracking = $this->extract($data, ['trackingNumber', 'awbNumber', 'shipmentNumber', 'awb']);
        $carrier = $this->extract($data, ['deliveryCompanyName', 'deliveryCompany', 'carrier']);
        $label = $this->extract($data, ['shippingLabel', 'labelURL', 'awbURL', 'label']);

        // createShipment can return a minimal body; enrich from orderDetails.
        if (! $tracking || ! $carrier || ! $label) {
            $details = $this->safeOrderDetails($order->order_number);
            $tracking ??= $this->extract($details, ['trackingNumber', 'awbNumber', 'shipmentNumber', 'awb']);
            $carrier ??= $this->extract($details, ['deliveryCompanyName', 'deliveryCompany', 'carrier']);
            $label ??= $this->extract($details, ['shippingLabel', 'labelURL', 'awbURL', 'label']);
        }

        if (! $tracking) {
            throw new RuntimeException('OTO shipment created but no tracking number was returned.');
        }

        return new NormalizedShipment(
            trackingNumber: $tracking,
            carrier: $carrier ?: 'OTO',
            labelUrl: $label,
            otoId: isset($data['otoId']) ? (int) $data['otoId'] : null,
            raw: $data,
        );
    }

    public function cancelShipment(Order $order): bool
    {
        $this->client->cancelShipment(['orderId' => $order->order_number]);

        return true;
    }

    /**
     * Everything OTO can currently offer this account, for the admin portal.
     *
     * Two endpoints, tried in order, because they are not equally available:
     *
     *  1. GET /getDeliveryOptions — the real catalogue. Rich (logo, pickup cut-off,
     *     weight allowance, return fee) but gated on the account's OTO plan tier.
     *  2. POST /checkOTODeliveryFee — the rate check we already use for shipping.
     *     Thinner, but it works on every plan.
     *
     * ⚠️ The fallback matters more than it looks. OTO documents (1) as available to
     * "Starter Package, Scale Package, Enterprise Package, Marketplaces" — i.e. NOT
     * the Free package — and this account's tier is unconfirmed, so (1) may simply
     * refuse. Treating that refusal as "no carriers available" would show the client
     * an empty portal and imply their shipping was broken, when in fact orders ship
     * fine. Degrading to a shorter list is the honest failure.
     *
     * Deliberately UNFILTERED by the enable flags: the portal has to show a
     * disabled carrier in order to offer the switch that turns it back on.
     *
     * @return CarrierOption[]
     */
    public function listServices(?string $city = null): array
    {
        $city = $city !== null && trim($city) !== '' ? trim($city) : $this->originCity;

        try {
            return $this->parseServices($this->client->deliveryOptions(['city' => $city]));
        } catch (\Throwable $e) {
            Log::info('OTO getDeliveryOptions unavailable, falling back to rate check', [
                'city' => $city,
                'reason' => $e->getMessage(),
            ]);
        }

        return $this->parseServices($this->client->checkDeliveryFee([
            'originCity' => $this->originCity,
            'destinationCity' => $city,
            'weight' => 1,
            'codAmount' => 0,
            'currency' => 'SAR',
        ]));
    }

    /**
     * Normalise either endpoint's body into CarrierOption[].
     *
     * Both wrap their rows differently and neither is documented as stable, so the
     * container key is probed rather than assumed — the same defensive shape the
     * rest of this class uses. A row with no carrier name is dropped: it could not
     * be matched to the register, so it could be neither enabled nor disabled.
     *
     * @return CarrierOption[]
     */
    private function parseServices(array $data): array
    {
        $rows = $data['deliveryOptions'] ?? $data['deliveryCompany'] ?? $data['data'] ?? $data['options'] ?? (array_is_list($data) ? $data : []);

        $options = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $carrier = $row['deliveryCompanyName'] ?? $row['deliveryCompany'] ?? $row['name'] ?? null;
            if (! $carrier) {
                continue;
            }

            $options[] = new CarrierOption(
                id: isset($row['deliveryOptionId']) ? (int) $row['deliveryOptionId'] : (isset($row['id']) ? (int) $row['id'] : null),
                carrier: (string) $carrier,
                // The listing names the SERVICE separately from the company; the rate
                // check does not, so this is often null and the portal shows the
                // company alone rather than inventing a service name.
                service: $row['deliveryOptionName'] ?? null,
                price: isset($row['price']) ? (float) $row['price'] : (isset($row['shippingFee']) ? (float) $row['shippingFee'] : null),
                currency: $row['currency'] ?? 'SAR',
                estimatedDelivery: $row['avgDeliveryTime'] ?? $row['deliveryTime'] ?? $row['estimatedDeliveryTime'] ?? null,
                pickupCutOff: $row['pickupCutOffTime'] ?? null,
                logo: $row['logo'] ?? null,
                maxOrderValue: isset($row['maxOrderValue']) ? (float) $row['maxOrderValue'] : null,
                maxFreeWeight: isset($row['maxFreeWeight']) ? (float) $row['maxFreeWeight'] : null,
                extraWeightPerKg: isset($row['extraWeightPerKg']) ? (float) $row['extraWeightPerKg'] : null,
                returnFee: isset($row['returnFee']) ? (float) $row['returnFee'] : null,
                pickupDropoff: isset($row['pickupDropoff']) ? (bool) $row['pickupDropoff'] : null,
            );
        }

        return $options;
    }

    public function verifyWebhookToken(?string $token): bool
    {
        if ($this->webhookSecret === '' || $token === null) {
            return false;
        }

        return hash_equals($this->webhookSecret, $token);
    }

    /**
     * Pull the first non-empty value for any of the given keys, searching one
     * level of nesting (OTO sometimes wraps payloads in `data`/`order`).
     */
    private function extract(array $data, array $keys): ?string
    {
        foreach ([$data, $data['data'] ?? [], $data['order'] ?? []] as $scope) {
            if (! is_array($scope)) {
                continue;
            }
            foreach ($keys as $key) {
                if (! empty($scope[$key])) {
                    return (string) $scope[$key];
                }
            }
        }

        return null;
    }

    private function safeOrderDetails(string $orderId): array
    {
        try {
            return $this->client->orderDetails($orderId);
        } catch (\Throwable) {
            return [];
        }
    }
}
