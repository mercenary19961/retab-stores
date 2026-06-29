<?php

namespace App\Services\Shipping\Oto;

use App\Models\Order;
use App\Services\Shipping\DeliveryOption;
use App\Services\Shipping\NormalizedShipment;
use App\Services\Shipping\ShippingGateway;
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
                'address' => trim(($address['building'] ?? '') . ' ' . ($address['street'] ?? '')) ?: ($address['district'] ?? 'N/A'),
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

            // No Aramex (business decision).
            if (stripos($carrier, 'aramex') !== false) {
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
