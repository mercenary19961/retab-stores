<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Retab's physical shop, shown as a map + directions so customers can navigate
 * to it. Deliberately static (no admin/table) — it's just a location page, not a
 * managed "branches" feature. Both locales ship so the AR⇄EN toggle is instant
 * (useLocalized), like the rest of the storefront.
 */
class BranchController
{
    /**
     * @var list<array<string, mixed>>
     *
     * 🔑 ONE shop, and that is the point of this list rather than an oversight.
     * Retab traded from two (Al Malqa and Al Aziziyah); Al Aziziyah is gone, so it
     * was removed here on 2026-08-31 along with every string that promised two.
     * The name deliberately dropped "Main Branch", which only means anything when
     * there are others.
     *
     * The coordinates are the Google Business listing's own
     * (maps.google.com → رطاب للتمور), so Directions lands exactly where the
     * customer would land searching for the shop by name.
     *
     * ⚠️ This is also the address the courier collects from, but OTO holds ITS OWN
     * copy of that (its pickup location), and this app only reads it. So the two
     * have to be kept in step by hand; /admin/shipping shows what OTO currently
     * holds so the drift is visible. See the shipping notes in CLAUDE.md.
     *
     * The page still renders a list, so putting a second shop back is one entry.
     */
    private const BRANCHES = [
        [
            'key' => 'malqa',
            'name_ar' => 'رطاب للتمور، الملقا',
            'name_en' => 'Retab Dates, Al Malqa',
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
    ];

    public function index(): Response
    {
        return Inertia::render('shop/branches', ['branches' => self::BRANCHES]);
    }
}
