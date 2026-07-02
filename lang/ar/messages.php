<?php

/*
 * Custom flash / error strings surfaced to users (storefront + admin), resolved
 * through __('messages.*') so they follow the request locale. Mirror en/messages.php.
 */

return [

    'cart' => [
        'added' => 'تمت الإضافة إلى السلة',
        'empty' => 'سلتك فارغة.',
    ],

    'review' => [
        'posted' => 'تم نشر تقييمك. شكراً لك!',
        'only_purchased' => 'يمكنك تقييم المنتجات التي اشتريتها فقط.',
        'no_self_vote' => 'لا يمكنك التصويت على تقييمك.',
        'anonymous' => 'عميل',
    ],

    'payment' => [
        'init_failed' => 'تعذّر بدء الدفع الإلكتروني. يمكنك إتمام الدفع عبر التحويل البنكي.',
    ],

    'profile' => [
        'updated' => 'تم تحديث بياناتك.',
    ],

    'otp' => [
        'rate_limited' => 'يرجى الانتظار قليلاً قبل طلب رمز جديد.',
        'invalid' => 'الرمز غير صحيح أو منتهي الصلاحية.',
    ],

    'admin' => [
        'order_confirmed' => 'تم تأكيد الطلب وخصم المخزون.',
        'order_unavailable' => 'تم تحديد الطلب كغير متوفر وإلغاء الحجز المالي.',
        'shipment_created' => 'تم إنشاء الشحنة وطلب الاستلام من الناقل.',
        'order_cancelled' => 'تم إلغاء الطلب.',
        'images_uploaded' => 'تم رفع الصور.',
        'image_deleted' => 'تم حذف الصورة.',
        'primary_image_set' => 'تم تعيين الصورة الرئيسية.',
        'product_created' => 'تمت إضافة المنتج.',
        'product_updated' => 'تم تحديث المنتج.',
        'product_deleted' => 'تم حذف المنتج.',
        'import_expired' => 'انتهت صلاحية ملف الاستيراد. يُرجى رفعه من جديد.',
        'import_applied' => 'تم تحديث المخزون: :count منتج.',
        'import_undone' => 'تم التراجع عن الاستيراد واستعادة المخزون السابق.',
    ],

];
