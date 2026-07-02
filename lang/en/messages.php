<?php

/*
 * Custom flash / error strings surfaced to users (storefront + admin), resolved
 * through __('messages.*') so they follow the request locale. Mirror ar/messages.php.
 */

return [

    'cart' => [
        'added' => 'Added to cart',
        'empty' => 'Your cart is empty.',
    ],

    'review' => [
        'posted' => 'Your review has been posted. Thank you!',
        'only_purchased' => 'You can only review products you have purchased.',
        'no_self_vote' => 'You can’t vote on your own review.',
        'anonymous' => 'Customer',
    ],

    'payment' => [
        'init_failed' => 'Could not start online payment. You can pay by bank transfer.',
    ],

    'profile' => [
        'updated' => 'Your details have been updated.',
    ],

    'otp' => [
        'rate_limited' => 'Please wait a moment before requesting a new code.',
        'invalid' => 'The code is incorrect or has expired.',
    ],

    'admin' => [
        'order_confirmed' => 'Order confirmed and stock deducted.',
        'order_unavailable' => 'Order marked unavailable and the payment hold released.',
        'shipment_created' => 'Shipment created and carrier pickup requested.',
        'order_cancelled' => 'Order cancelled.',
        'images_uploaded' => 'Images uploaded.',
        'image_deleted' => 'Image deleted.',
        'primary_image_set' => 'Primary image set.',
        'product_created' => 'Product added.',
        'product_updated' => 'Product updated.',
        'product_deleted' => 'Product deleted.',
        'import_expired' => 'The import file has expired. Please upload it again.',
        'import_applied' => 'Stock updated: :count products.',
        'import_undone' => 'Import undone and previous stock restored.',
    ],

];
