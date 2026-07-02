<?php

namespace App\Enums;

/**
 * A single payment-gateway transaction against an order. Cards (Moyasar) use
 * Capture/Refund; Tamara uses Authorization → Capture, or Void before capture.
 */
enum PaymentTransactionType: string
{
    case Authorization = 'authorization';
    case Capture = 'capture';
    case Void = 'void';
    case Refund = 'refund';
}
