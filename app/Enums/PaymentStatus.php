<?php

namespace App\Enums;

/**
 * Payment lifecycle, kept separate from the order's fulfillment status.
 * Cards (Moyasar) capture immediately (→ Paid); Tamara authorizes first
 * (→ Authorized) and captures on admin confirmation (→ Paid).
 */
enum PaymentStatus: string
{
    case Pending = 'pending';                      // no payment attempt completed yet
    case Authorized = 'authorized';                // Tamara hold placed, not yet captured
    case Paid = 'paid';                            // captured / funds collected
    case Refunded = 'refunded';                    // fully refunded
    case PartiallyRefunded = 'partially_refunded'; // e.g. items refunded but shipping kept
    case Voided = 'voided';                        // authorization released without capture
    case Failed = 'failed';                        // payment attempt failed
}
