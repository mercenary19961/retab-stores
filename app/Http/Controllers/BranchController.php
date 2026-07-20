<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Retab's physical shops, shown as a map + directions so customers can navigate
 * to them. Deliberately static (no admin/table) — it's just a locations page,
 * not a managed "branches" feature. Both locales ship so the AR⇄EN toggle is
 * instant (useLocalized), like the rest of the storefront.
 */
class BranchController
{
    /** @var list<array<string, mixed>> */
    private const BRANCHES = [
        [
            'key' => 'malqa',
            'name_ar' => 'الفرع الرئيسي — الملقا',
            'name_en' => 'Main Branch — Al Malqa',
            'address_ar' => 'مقابل نادي الشباب، طريق الملك فهد الفرعي، الملقا، الرياض 11564',
            'address_en' => 'Opposite Al Shabab Club, King Fahd Branch Rd, Al Malqa, Riyadh 11564',
            'phone' => '+966503326600',
            'hours_ar' => 'يومياً حتى 11 مساءً',
            'hours_en' => 'Daily until 11 PM',
            'lat' => 24.8016265,
            'lng' => 46.6263008,
            'rating' => 4.5,
            'reviews' => 1124,
        ],
        [
            'key' => 'aziziyah',
            'name_ar' => 'فرع العزيزية',
            'name_en' => 'Al Aziziyah Branch',
            'address_ar' => 'الطريق الدائري الجنوبي الفرعي، العزيزية، الرياض 11961',
            'address_en' => 'Southern Ring Branch Rd, Al Aziziyah, Riyadh 11961',
            'phone' => '+966550883845',
            'hours_ar' => 'يومياً حتى 1 صباحاً (يعيد الفتح 4 مساءً)',
            'hours_en' => 'Daily until 1 AM (reopens 4 PM)',
            'lat' => 24.5950365,
            'lng' => 46.7399642,
            'rating' => 4.3,
            'reviews' => 72,
        ],
    ];

    public function index(): Response
    {
        return Inertia::render('shop/branches', ['branches' => self::BRANCHES]);
    }
}
