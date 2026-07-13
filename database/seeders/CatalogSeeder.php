<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Two nav parent groups — these drive the storefront navbar dropdowns
        // (التمور / الهدايا). Placeholder taxonomy; the Zid import will replace it.
        $parents = [
            'cat-dates' => ['name_ar' => 'التمور', 'name_en' => 'Dates'],
            'cat-gifts' => ['name_ar' => 'الهدايا', 'name_en' => 'Gifts'],
        ];

        $parentModels = [];
        foreach (array_keys($parents) as $i => $slug) {
            $parentModels[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                $parents[$slug] + ['parent_id' => null, 'sort_order' => $i, 'is_active' => true],
            );
        }

        // Leaf categories (products attach here), each grouped under a nav parent.
        // The former flat 'dates' leaf is relabelled "تمور فاخرة" so it doesn't
        // duplicate the "التمور" parent label inside the dropdown.
        $categories = [
            ['slug' => 'dates', 'name_ar' => 'تمور فاخرة', 'name_en' => 'Premium Dates', 'parent' => 'cat-dates'],
            ['slug' => 'stuffed-dates', 'name_ar' => 'التمور المحشية', 'name_en' => 'Stuffed Dates', 'parent' => 'cat-dates'],
            ['slug' => 'boxes', 'name_ar' => 'البوكسات', 'name_en' => 'Gift Boxes', 'parent' => 'cat-gifts'],
            ['slug' => 'occasion-gifts', 'name_ar' => 'هدايا المناسبات', 'name_en' => 'Occasion Gifts', 'parent' => 'cat-gifts'],
            ['slug' => 'assorted', 'name_ar' => 'منتجات متنوعة', 'name_en' => 'Assorted Products', 'parent' => 'cat-gifts'],
        ];

        $cats = [];
        foreach ($categories as $i => $c) {
            $cats[$c['slug']] = Category::updateOrCreate(
                ['slug' => $c['slug']],
                [
                    'name_ar' => $c['name_ar'],
                    'name_en' => $c['name_en'],
                    'parent_id' => $parentModels[$c['parent']]->id,
                    'sort_order' => $i,
                    'is_active' => true,
                ],
            );
        }

        // Weight lives in the title per the spec (descriptive, not a structured field).
        $products = [
            ['cat' => 'dates', 'slug' => 'sukkari-1kg', 'name_ar' => 'تمر سكري فاخر - 1 كجم', 'name_en' => 'Premium Sukkari Dates - 1kg', 'price' => 75, 'sku' => 'SUK-1KG', 'stock' => 120, 'featured' => true],
            ['cat' => 'dates', 'slug' => 'ajwa-1kg', 'name_ar' => 'تمر عجوة المدينة - 1 كجم', 'name_en' => 'Ajwa Al-Madinah - 1kg', 'price' => 95, 'sku' => 'AJW-1KG', 'stock' => 80, 'featured' => true],
            ['cat' => 'dates', 'slug' => 'khalas-1kg', 'name_ar' => 'تمر خلاص - 1 كجم', 'name_en' => 'Khalas Dates - 1kg', 'price' => 55, 'sku' => 'KHL-1KG', 'stock' => 150],
            ['cat' => 'dates', 'slug' => 'medjool-500g', 'name_ar' => 'تمر مجدول فاخر - 500 جم', 'name_en' => 'Premium Medjool - 500g', 'price' => 65, 'sale' => 55, 'sku' => 'MJD-500', 'stock' => 60],
            ['cat' => 'stuffed-dates', 'slug' => 'stuffed-nuts-500g', 'name_ar' => 'تمر محشي بالمكسرات - 500 جم', 'name_en' => 'Nut-stuffed Dates - 500g', 'price' => 85, 'sku' => 'STF-NUT', 'stock' => 70],
            ['cat' => 'stuffed-dates', 'slug' => 'stuffed-choc-500g', 'name_ar' => 'تمر محشي بالشوكولاتة - 500 جم', 'name_en' => 'Chocolate-stuffed Dates - 500g', 'price' => 90, 'sku' => 'STF-CHC', 'stock' => 65],
            ['cat' => 'boxes', 'slug' => 'luxury-box', 'name_ar' => 'بوكس تمور فاخر مشكّل', 'name_en' => 'Luxury Assorted Dates Box', 'price' => 180, 'sku' => 'BOX-LUX', 'stock' => 40, 'featured' => true],
            ['cat' => 'occasion-gifts', 'slug' => 'ramadan-gift', 'name_ar' => 'هدية رمضان - تمور وقهوة', 'name_en' => 'Ramadan Gift - Dates & Coffee', 'price' => 220, 'sku' => 'GFT-RMD', 'stock' => 30],
            ['cat' => 'assorted', 'slug' => 'arabic-coffee-250g', 'name_ar' => 'قهوة عربية فاخرة - 250 جم', 'name_en' => 'Premium Arabic Coffee - 250g', 'price' => 45, 'sku' => 'COF-250', 'stock' => 100],
            ['cat' => 'assorted', 'slug' => 'sidr-honey-500g', 'name_ar' => 'عسل سدر طبيعي - 500 جم', 'name_en' => 'Natural Sidr Honey - 500g', 'price' => 160, 'sku' => 'HNY-500', 'stock' => 50],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'category_id' => $cats[$p['cat']]->id,
                    'name_ar' => $p['name_ar'],
                    'name_en' => $p['name_en'],
                    'description_ar' => $p['name_ar'] . ' — منتج فاخر من رطاب للتمور.',
                    'description_en' => $p['name_en'] . ' — a premium product from Retab Dates.',
                    'price' => $p['price'],
                    'sale_price' => $p['sale'] ?? null,
                    'sku' => $p['sku'],
                    'smacc_sku' => 'SM-' . $p['sku'],
                    'stock' => $p['stock'],
                    'is_active' => true,
                    'is_featured' => $p['featured'] ?? false,
                ],
            );
        }
    }
}
