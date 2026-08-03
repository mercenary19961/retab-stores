<?php

/*
 * Customer-facing transactional email copy. Mirror ar/emails.php.
 *
 * ⚠️ Unlike the STAFF notifications (which are deliberately hard-coded English —
 * see NewOrderNotification), these must follow the customer's own language. They
 * render in `orders.locale`, the language snapshotted at checkout.
 */

return [

    'common' => [
        'greeting' => 'Hello :name,',
        'order_number' => 'Order number',
        'view_order' => 'View your order',
        'subtotal' => 'Subtotal',
        'discount' => 'Discount',
        'shipping' => 'Shipping',
        'total' => 'Total',
        'currency' => 'SAR',
        'qty' => 'Qty',
        'help' => 'Need help? Reply to this email or message us on WhatsApp at :phone.',
        'footer_rights' => 'All rights reserved.',
    ],

    'placed' => [
        'subject' => 'We received your order :number',
        'heading' => 'Thank you for your order',
        'intro' => 'We have received your order and it is now being reviewed by our team. We will let you know as soon as it is confirmed.',
        'bank_heading' => 'Complete your bank transfer',
        'bank_intro' => 'Your order is reserved. To complete it, transfer :amount SAR to the account below and include your order number :number as the transfer reference.',
        'bank_name' => 'Bank',
        'bank_beneficiary' => 'Beneficiary',
        'bank_iban' => 'IBAN',
        'bank_account' => 'Account number',
        'bank_after' => 'Once we verify the transfer, we will confirm your order and prepare it for shipping.',
    ],

    'confirmed' => [
        'subject' => 'Your order :number is confirmed',
        'heading' => 'Your order is confirmed',
        'intro' => 'Good news — we have confirmed your order and it is being prepared. You will get another email with tracking details once it is on its way.',
    ],

    'shipped' => [
        'subject' => 'Your order :number is on its way',
        'heading' => 'Your order has shipped',
        'intro' => 'Your order is on its way. You can follow it with the details below.',
        'carrier' => 'Carrier',
        'tracking_number' => 'Tracking number',
    ],

];
