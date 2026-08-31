<?php

namespace App\Services\Shipping;

use App\Models\Order;

/**
 * Contract every shipping aggregator (OTO/Tryoto, Torod, ...) implements.
 * Keeping fulfillment behind this interface means swapping/adding a provider
 * later only touches the binding in AppServiceProvider.
 */
interface ShippingGateway
{
    /**
     * Push the order to the aggregator (so it knows about it before shipment).
     * Returns the aggregator's internal order id.
     */
    public function pushOrder(Order $order): int;

    /**
     * Available carrier options + prices for shipping this order.
     *
     * @return DeliveryOption[]
     */
    public function getDeliveryOptions(Order $order): array;

    /**
     * Create the actual shipment with the chosen carrier option and return the
     * tracking details (number, carrier, label).
     */
    public function createShipment(Order $order, int $deliveryOptionId): NormalizedShipment;

    /**
     * Cancel a shipment previously created for this order.
     */
    public function cancelShipment(Order $order): bool;

    /**
     * Every shipping service the aggregator can currently offer this account,
     * with prices and terms — the catalogue behind /admin/shipping.
     *
     * Distinct from getDeliveryOptions(): that quotes ONE order and is filtered
     * to what the store will actually ship with, whereas this lists everything
     * available so the admin can decide what to switch on. Never filtered, or the
     * portal could not show a disabled carrier at all.
     *
     * @param  string|null  $city  Destination to price against; null = the origin city.
     * @return CarrierOption[]
     */
    public function listServices(?string $city = null): array;

    /**
     * Constant-time check that a webhook secret matches ours.
     */
    public function verifyWebhookToken(?string $token): bool;
}
