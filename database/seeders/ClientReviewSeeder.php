<?php

namespace Database\Seeders;

use App\Models\ClientReview;
use Illuminate\Database\Seeder;

class ClientReviewSeeder extends Seeder
{
    public function run(): void
    {
        // Placeholder pool — the admin replaces these with real curated Google
        // Maps reviews. Keyed by author_name so re-seeding is idempotent.
        $reviews = [
            ['author_name' => 'Mohammad Ahmad', 'language' => 'en', 'rating' => 5, 'body' => 'Great variety of Saudi dates. The packaging is very nice and the prices are reasonable.'],
            ['author_name' => 'Sarah Al-Otaibi', 'language' => 'en', 'rating' => 5, 'body' => 'Ordered a gift box for Ramadan and it arrived beautifully wrapped and on time. Highly recommend.'],
            ['author_name' => 'خالد الزهراني', 'language' => 'ar', 'rating' => 5, 'body' => 'تمور فاخرة وطازجة، والتغليف أنيق جداً. تجربة شراء ممتازة وسرعة في التوصيل.'],
            ['author_name' => 'نورة القحطاني', 'language' => 'ar', 'rating' => 5, 'body' => 'أفضل تمر سكري جربته، الجودة عالية والخدمة راقية. أنصح فيهم بشدة.'],
            ['author_name' => 'Abdullah Saleh', 'language' => 'en', 'rating' => 5, 'body' => 'Excellent quality and fast delivery across the GCC. The Ajwa dates were fresh and delicious.'],
            ['author_name' => 'فاطمة العنزي', 'language' => 'ar', 'rating' => 4, 'body' => 'منتجات ممتازة وتغليف جميل يليق بالهدايا. أتمنى لو كانت خيارات الدفع أكثر.'],
            ['author_name' => 'Yousef Al-Harbi', 'language' => 'en', 'rating' => 5, 'body' => 'Premium dates with a premium experience. Customer service answered all my questions quickly.'],
            ['author_name' => 'ريم المطيري', 'language' => 'ar', 'rating' => 5, 'body' => 'طلبت بوكس تمور مشكّل وكان أكثر من رائع، طعم أصيل وتغليف فخم. شكراً رطاب.'],
        ];

        foreach ($reviews as $i => $r) {
            ClientReview::updateOrCreate(
                ['author_name' => $r['author_name'], 'body' => $r['body']],
                $r + ['source' => 'manual', 'is_active' => true, 'sort_order' => $i],
            );
        }
    }
}
