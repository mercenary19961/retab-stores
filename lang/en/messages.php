<?php

/*
 * Custom flash / error strings surfaced to users (storefront + admin), resolved
 * through __('messages.*') so they follow the request locale. Mirror ar/messages.php.
 */

return [

    'cart' => [
        'added' => 'Added to cart',
        'empty' => 'Your cart is empty.',
        'coupon_applied' => 'Coupon applied.',
        'coupon_removed' => 'Coupon removed.',
    ],

    /*
     * Coupon errors are shown to the SHOPPER (cart page + checkout), so they must
     * be localized — they were hard-coded English literals until 2026-08-06.
     */
    'checkout' => [
        'coupon_invalid' => 'This coupon code is invalid or has expired.',
        'coupon_not_yours' => 'This coupon is not available for your account.',
        'coupon_used_up' => 'You have already used this coupon the maximum number of times.',
    ],

    'review' => [
        'posted' => 'Your review has been posted. Thank you!',
        'reward_issued' => 'Thanks for your review! Your :percent% discount code :code is on your account, valid for 30 days.',
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

    'security' => [
        'verify_failed' => 'We couldn’t verify you’re not a robot. Please try again.',
    ],

    'otp' => [
        'rate_limited' => 'Please wait a moment before requesting a new code.',
        'invalid' => 'The code is incorrect or has expired.',
    ],

    'requests' => [
        'received' => 'Thanks! We’ve noted your interest and will let you know as soon as it’s available.',
    ],

    'returns' => [
        'filed' => 'Your return request was received. We will review it and get back to you soon.',
        'not_yours' => 'This order does not belong to you.',
        'not_delivered' => 'Returns can only be requested after delivery.',
        'window_expired' => 'The return window (3 days from delivery) has expired.',
        'already_filed' => 'A return request already exists for this order.',
        'no_items' => 'Select at least one item to return.',
        'invalid_items' => 'The return items are invalid.',
        'invalid_transition' => 'This action is not allowed in the return’s current state.',
    ],

    'marketing' => [
        'template_saved' => 'Template saved.',
        'campaign_queued' => 'Campaign queued for sending.',
        'already_sent' => 'This campaign was already sent.',
        'template_not_approved' => 'Only Meta-approved templates can be sent.',
        'no_audience' => 'No customers are opted in to the marketing list.',
        'params_mismatch' => 'The number of variables does not match the template.',
    ],

    'admin' => [
        'settings_saved' => 'Settings saved.',
        'content_reset' => 'Content restored to the project-handover defaults.',
        'no_permission' => 'You do not have permission to perform this action.',
        'permissions_updated' => 'Permissions updated for :name.',
        'editor_created' => 'Editor account created for :name.',
        'editor_deleted' => 'Editor account removed.',
        'page_saved' => 'Page saved.',
        'review_saved' => 'Review saved.',
        'review_deleted' => 'Review deleted.',
        'coupon_saved' => 'Coupon saved.',
        'coupon_deleted' => 'Coupon deleted.',
        'coupon_activated' => 'Coupon activated.',
        'coupon_deactivated' => 'Coupon deactivated.',
        'coupon_has_redemptions' => 'This coupon has already been used, so it cannot be deleted. Deactivate it instead.',
        'reviews_imported' => ':count review(s) imported.',
        'return_approved' => 'Return request approved.',
        'return_rejected' => 'Return request rejected.',
        'return_exchanged' => 'Return closed as an exchange.',
        'return_refunded' => 'Refund executed and return closed.',
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
        'product_activated' => 'Product is now visible on the store.',
        'product_deactivated' => 'Product hidden from the store.',
        'product_needs_image' => 'A product must have at least one image.',
        'request_handled' => 'Request marked as handled.',
        'import_expired' => 'The import file has expired. Please upload it again.',
        'import_applied' => 'Stock updated: :count products.',
        'import_undone' => 'Import undone and previous stock restored.',
        'import_open_failed' => 'Could not open the uploaded file.',
        'import_empty' => 'The file is empty.',
        'discount_applied' => 'Discount applied to :count products.',
        'discount_cleared' => 'Discount cleared from :count products.',
        'discount_undone' => 'Discount change undone.',
        'discount_cannot_undo' => 'This discount change can no longer be undone.',
        'discount_none_to_clear' => 'No matching discounted products to clear.',
        'discount_import_columns' => 'The file needs a sku column and a discount_percent column.',
        'free_shipping_saved' => 'Free shipping settings saved.',
        'review_reward_saved' => 'Review reward settings saved.',
        'change_reverted' => 'Change reverted.',
        'change_revert_conflict' => 'Cannot revert: :fields changed again after this entry. Review the current values instead.',
        'change_revert_blocked' => 'This change can no longer be reverted.',
    ],

];
