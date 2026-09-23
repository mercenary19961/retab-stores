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
        // Appended to the lead product's name in a subject when the order has more lines.
        'and_more' => '{1} and 1 more item|[2,*] and :count more items',
    ],

    'placed' => [
        'subject' => 'We received your order: :items',
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
        'subject' => 'Your order is confirmed: :items',
        'heading' => 'Your order is confirmed',
        'intro' => 'Good news — we have confirmed your order and it is being prepared. You will get another email with tracking details once it is on its way.',
    ],

    'shipped' => [
        'subject' => 'Your order is on its way: :items',
        'heading' => 'Your order has shipped',
        'intro' => 'Your order is on its way. You can follow it with the details below.',
        'carrier' => 'Carrier',
        'tracking_number' => 'Tracking number',
    ],

    /*
     * Staff alert emails. Sent in ARABIC today (see
     * App\Notifications\Concerns\SendsStaffMail); kept mirrored here so a
     * per-account language can be switched on later without new copy.
     */
    'staff' => [
        'order_number' => 'Order',
        'customer' => 'Customer',
        'total' => 'Total',
        'open_order' => 'Open the order',
        'product' => 'Product',
        'contact' => 'Contact',

        'new_order' => [
            'subject' => 'New order: :items',
            'subject_plain' => 'New order :number needs confirmation',
            'heading' => 'A new order needs your confirmation',
            'intro' => 'A new order has been placed and is waiting for your review.',
            'note' => 'Check stock, then confirm or reject it. Card payments are captured immediately and Tamara authorizations expire, so please review within 24 hours.',
        ],

        'order_cancelled' => [
            'subject' => 'Order :number was cancelled by the customer',
            'heading' => 'An order was cancelled',
            'intro' => ':name cancelled order :number.',
            'note' => 'Stop any preparation for it. Any payment has already been released automatically.',
        ],

        'payment_expiring' => [
            'subject' => 'Action needed: the Tamara authorization on order :number expires in about :hours',
            'heading' => 'A Tamara authorization is about to expire',
            'intro' => 'The Tamara authorization on order :number lapses in about :hours.',
            'hours' => '{0} less than an hour|{1} 1 hour|[2,*] :count hours',
            'action' => 'Confirm or reject the order',
            'note' => 'Confirming captures the money. Rejecting releases the hold cleanly. Doing nothing loses the sale.',
        ],

        'return_requested' => [
            'subject' => 'Return requested for order :number',
            'heading' => 'A new return request',
            'intro' => 'A customer filed a return for order :number.',
            'reason' => 'Reason',
            'action' => 'Review the return',
            'note' => 'Returns are for defects or damage only and must be filed within 3 days of delivery. The photos are on the review page.',
        ],

        'product_requested' => [
            'subject' => 'Someone wants a coming-soon product',
            'heading' => 'A coming-soon product was requested',
            'intro' => 'A customer registered interest in a product that is not on sale yet.',
            'action' => 'Open product requests',
            'note' => 'Follow up on WhatsApp, then mark the request handled.',
        ],

        'contact_message' => [
            'subject' => 'New contact form message',
            'heading' => 'A new message from the Contact Us page',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'inquiry_type' => 'Inquiry type',
            'message' => 'Message',
            'action' => 'Open messages',
        ],

        'inquiry_types' => [
            'order' => 'Order inquiry',
            'product' => 'Product inquiry',
            'complaint' => 'Complaint',
            'partnership' => 'Partnership',
            'other' => 'Other',
        ],
    ],

    'sensitive' => [
        'subject' => 'Confirm deleting the bank and registration details',
        'heading' => 'Confirm deleting the bank and registration details',
        'line_1' => 'Someone signed in as the store owner asked to delete the bank account number, the IBAN, the commercial registration and the VAT number from the store settings.',
        'line_2' => 'Nothing has been deleted yet. Use the button below to confirm.',
        'action' => 'Confirm deletion',
        'note' => 'This link works once and expires in :minutes minutes. If you did not ask for this, do not open it, and change the owner password.',
    ],

    'unavailable' => [
        'subject' => 'About your order: :items',
        'heading' => 'We could not complete your order',
        'intro' => 'We are sorry. After checking our stock we were not able to fulfil this order, so we have cancelled it.',
        'refund_heading' => 'Your money is on its way back',
        'refund_card' => 'The amount has been refunded to the card you paid with. Banks usually take 5 to 10 working days to show it.',
        'refund_tamara' => 'Your Tamara instalment plan has been cancelled, so there is nothing further to pay.',
        'refund_transfer' => 'We will transfer the amount back to you. Please reply to this email with your IBAN if we do not already have it.',
        'sorry' => 'We would still like to serve you. Get in touch and we will suggest something similar, or let you know when this is back in stock.',
    ],
    'delivered' => [
        'subject' => 'Your order has been delivered: :items',
        'heading' => 'Your order has been delivered',
        'intro' => 'Your order has arrived. We hope you enjoy it.',
        'returns_heading' => 'If something is wrong',
        'returns_intro' => 'Please check your order. If anything arrived damaged or faulty, tell us within :days days of delivery and we will put it right.',
    ],
    'return_update' => [
        'subject' => 'Your return request: :status',
        'heading' => 'Your return request has been updated',
        'intro' => 'Here is the latest on the return you filed for this order.',
        'status_label' => 'Status',
        'statuses' => [
            'requested' => 'Received',
            'approved' => 'Approved',
            'rejected' => 'Not approved',
            'exchanged' => 'Exchanged',
            'refunded' => 'Refunded',
        ],
        'notes' => [
            'requested' => 'We have received your request and our team is reviewing the photos you sent. We will be in touch shortly.',
            'approved' => 'We have approved your return. We will contact you to arrange collecting the items and completing the exchange or refund.',
            'rejected' => 'After reviewing it, we were not able to approve this return. Reply to this email if you would like us to look again.',
            'exchanged' => 'Your exchange is being arranged. We will send the replacement shortly.',
            'refunded' => 'Your refund has been issued. Banks usually take 5 to 10 working days to show it on your statement.',
        ],
    ],
];
