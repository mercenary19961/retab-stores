<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use Illuminate\Database\Seeder;

/**
 * The three baseline CMS pages. Idempotent (keyed by slug) and non-destructive:
 * existing rows are left untouched so admin edits survive re-seeding.
 */
class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::pages() as $page) {
            ContentPage::firstOrCreate(
                ['slug' => $page['slug']],
                [...$page, 'is_published' => true],
            );
        }
    }

    /**
     * The three baseline CMS pages' handover content. Shared by the seeder and
     * the admin "reset to handover defaults" safeguard.
     *
     * @return list<array<string, string>>
     */
    public static function pages(): array
    {
        return [
            [
                'slug' => 'returns-policy',
                'title_ar' => 'سياسة الاستبدال والاسترجاع',
                'title_en' => 'Returns & Exchange Policy',
                'body_ar' => "يُقبل الاسترجاع أو الاستبدال في حال وجود عيوب في المنتج أو وصول التمور تالفة فقط.\n\n• يجب تقديم طلب الإرجاع خلال 3 أيام من استلام المنتج.\n• يتم تقديم الطلب من صفحة الطلب في حسابك مع إرفاق صور توضح المشكلة، أو عبر واتساب قسم الاسترجاع: ‎+966 50 384 5356.\n• يجب أن تكون المنتجات المرتجعة بحالة جيدة مع الملصقات والتغليف الأصلي.\n• رسوم الشحن غير قابلة للاسترداد، إلا إذا وصلت التمور تالفة.\n• بعد فحص المنتج، يتم الاستبدال أو استرجاع المبلغ خلال 14 يوماً.\n\nلا يشمل الاسترجاع: التأخر في الاستلام، أخطاء شركة الشحن، أو المنتجات المستخدمة أو التي عُبث بتغليفها.",
                'body_en' => "Returns or exchanges are accepted only for product defects or dates that arrived damaged.\n\n• The return request must be filed within 3 days of receiving the product.\n• File it from your order page in your account with photos showing the problem, or via WhatsApp (returns dept.): +966 50 384 5356.\n• Returned items must be in good condition with the original labels and packaging.\n• Shipping fees are non-refundable, unless the dates arrived damaged.\n• After inspection, we exchange the product or refund within 14 days.\n\nNot covered: late receipt, shipping-company faults, or products that were used or had their packaging tampered with.",
            ],
            [
                'slug' => 'about',
                'title_ar' => 'من نحن',
                'title_en' => 'About Us',
                'body_ar' => "شركة مصنع رطاب الوطن للتمور — تمور فاخرة من قلب المملكة العربية السعودية.\n\n(يُحرَّر هذا المحتوى من لوحة التحكم.)",
                'body_en' => "Retab Alwatan Dates Factory — premium dates from the heart of Saudi Arabia.\n\n(Edit this content from the admin panel.)",
            ],
            [
                'slug' => 'contact',
                'title_ar' => 'تواصل معنا',
                'title_en' => 'Contact Us',
                'body_ar' => "واتساب: ‎+966 50 384 5356\n\n(يُحرَّر هذا المحتوى من لوحة التحكم.)",
                'body_en' => "WhatsApp: +966 50 384 5356\n\n(Edit this content from the admin panel.)",
            ],
        ];
    }
}
