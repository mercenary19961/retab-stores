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

    'security' => [
        'verify_failed' => 'تعذّر التحقق من أنك لست روبوتاً. يُرجى المحاولة مجدداً.',
    ],

    'otp' => [
        'rate_limited' => 'يرجى الانتظار قليلاً قبل طلب رمز جديد.',
        'invalid' => 'الرمز غير صحيح أو منتهي الصلاحية.',
    ],

    'returns' => [
        'filed' => 'تم استلام طلب الإرجاع. سنراجعه ونتواصل معك قريباً.',
        'not_yours' => 'هذا الطلب لا يخصك.',
        'not_delivered' => 'يمكن طلب الإرجاع بعد استلام الطلب فقط.',
        'window_expired' => 'انتهت مهلة الإرجاع (3 أيام من الاستلام).',
        'already_filed' => 'يوجد طلب إرجاع قائم لهذا الطلب.',
        'no_items' => 'اختر منتجاً واحداً على الأقل للإرجاع.',
        'invalid_items' => 'عناصر الإرجاع غير صالحة.',
        'invalid_transition' => 'لا يمكن تنفيذ هذا الإجراء في حالة الطلب الحالية.',
    ],

    'marketing' => [
        'template_saved' => 'تم حفظ القالب.',
        'campaign_queued' => 'تم إرسال الحملة إلى قائمة الانتظار.',
        'already_sent' => 'هذه الحملة أُرسلت من قبل.',
        'template_not_approved' => 'لا يمكن الإرسال إلا بقالب معتمد من ميتا.',
        'no_audience' => 'لا يوجد عملاء مشتركون في القائمة التسويقية.',
        'params_mismatch' => 'عدد المتغيرات لا يطابق القالب.',
    ],

    'admin' => [
        'settings_saved' => 'تم حفظ الإعدادات.',
        'page_saved' => 'تم حفظ الصفحة.',
        'review_saved' => 'تم حفظ التقييم.',
        'review_deleted' => 'تم حذف التقييم.',
        'return_approved' => 'تمت الموافقة على طلب الإرجاع.',
        'return_rejected' => 'تم رفض طلب الإرجاع.',
        'return_exchanged' => 'تم إغلاق الإرجاع كاستبدال.',
        'return_refunded' => 'تم تنفيذ الاسترجاع المالي وإغلاق الطلب.',
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
        'change_reverted' => 'تم التراجع عن التغيير.',
        'change_revert_conflict' => 'تعذر التراجع: :fields تغيّرت مرة أخرى بعد هذا السجل. يُرجى مراجعة القيم الحالية.',
        'change_revert_blocked' => 'لم يعد بالإمكان التراجع عن هذا التغيير.',
    ],

];
