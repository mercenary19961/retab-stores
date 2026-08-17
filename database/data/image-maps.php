<?php

/**
 * Named maps for `php artisan catalog:import-images <group> --dir=...`, used to
 * attach better client-supplied product photos, one category at a time.
 *
 * Each entry: product slug => [ include-all, exclude-any ] — lists of substrings
 * matched (Arabic-aware) against image FILENAMES. A file is assigned to a product
 * when it contains EVERY "include" substring and NONE of the "exclude" ones. The
 * same file set can map to more than one slug (e.g. two pack sizes of one rusk).
 */
return [
    // Client رطاب rusk (شابورة) photos → the الشوابير products (received 2026-07-20).
    // بقسماط (#70) is breadcrumbs, not شابورة, so it's intentionally absent.
    'rusks' => [
        'شابورة-بالبر' => [['بر'], ['قمح', 'حبوب']],              // bran only
        'شابورة-بالقمح-1' => [['قمح'], ['كامل']],                 // plain wheat (9 SAR pack)
        'شابورة-بالقمح' => [['قمح'], ['كامل']],                   // plain wheat (11.50 SAR pack) — same photos
        'شابورة-بالقمح-الكامل-بر' => [['قمح', 'كامل'], []],       // whole wheat + bran
        'شابورة-بالحبوب' => [['حبوب'], ['نخالة', 'عسل']],         // grains only
        'شابورة-بالحبوب-و-النخالة' => [['حبوب', 'نخالة'], []],    // grains + bran
        'شابورة-أصل-الرشاقة-1' => [['حبوب', 'عسل'], []],          // grains + honey + milk
    ],

    /*
     * Studio photoshoot received 2026-08-17 (24 shots, grey stone backdrop + palm
     * shadow + dallah props). Sources live in database/data/product-photos/ as
     * retab-01..24.webp — IN the repo deliberately, because this import also has
     * to run on production, where the container has nowhere else to read them
     * from (same reason as the preview-products set).
     *
     * ⚠️ Filenames are zero-padded, which is what makes a bare 'retab-12' an
     * unambiguous matcher — without the padding it would also catch retab-1.
     *
     * ⚠️ The importer REPLACES a product's existing images, so a slug listed here
     * loses its old Zid photos. That is the intent (these are far better), but it
     * is destructive: check the product's current image count before adding a slug.
     */
    'photoshoot-2026-08' => [
        // — Pantry / non-date lines, each identified from its own printed label —
        'اقط' => [['retab-01'], []],                    // Retab tub, أقط
        'دبس-رطاب' => [['retab-02'], []],               // Retab-branded دبس التمر bottle
        'طحينة-عصار' => [['retab-03'], []],             // bottle reads طحينة فاخرة / Premium Tahina
        'قهوة-نجدية' => [['retab-04'], []],             // teal jar + dallah
        'بوكس-مكسرات-مشكل' => [['retab-06'], []],       // hex box, مكسرات فاخرة / LUXURY NUTS
        'مقلوبة' => [['retab-10'], []],                 // box + tub + plated slice, مقلوبة تمر PREMIUM
        'تمرية-رطاب' => [['retab-11'], []],             // red نمرية gift box + sesame-ball tub

        // — Stuffed-date boxes. Matched on BOX COLOUR against the live storefront
        //   photos: #12 is the purple box, #13 and #14 the white/gold ones. —
        'بوكس-سكري-محشي-لوز-و-شرائح-اللوز' => [['retab-12'], []],  // purple box, pistachio + almond slivers
        'بوكس-سكري-محشي-بالكريمة' => [['retab-13'], []],           // white box, mixed toppings
        'بوكس-تمر-محشي-مشكل' => [['retab-14'], []],                // white box, pistachio + walnut halves
        'صينية-تمر-محشي' => [['retab-16'], []],                    // large white tray, pistachio + almonds only

        // — Dates, all three read straight off the pack's own printed name —
        'صقعي-فاخر' => [['retab-22'], []],              // SAQI DATES / تمر صقعي 1kg PREMIUM
        'عجوة-المدينة-فله-فاخر' => [['retab-23'], []],  // AJWA DATES / تمر عجوة 1kg PREMIUM
        // One photo, two SKUs: every dates line exists as a كرتون/حبة pair of the
        // same physical product, so both share the shot.
        'سكري-فاخر-١-كيلو-كرتون' => [['retab-24'], []], // SUGAR DATES / تمر سكري 1kg PREMIUM
        'سكري-فاخر-١-كيلو-حبة' => [['retab-24'], []],

        // — قدوع. The tin's label reads only "قدوع", not which flavour, so the
        //   client asked for the same shot on all three until they supply
        //   per-flavour photos. —
        'قدوع-رطاب-سكري' => [['retab-09'], []],
        'قدوع-رطاب-خلاص' => [['retab-09'], []],
        'قدوع-رطاب-بالكراميل-المملح' => [['retab-09'], []],

        // Cellophane gift bag of individually wrapped pieces — confirmed against
        // the live storefront photo, which is the same bag on the same tray.
        'بوكس-التمرة-الذهبية' => [['retab-15'], []],

        // Pressed block of hand-ground dates.
        'سكري-يدوي-مجروش-فاخر-1-كيلو' => [['retab-17'], []],

        /*
         * — 250g Khalas Ushaiger: the display CARTON (#18) and a single vacuum
         *   PACK (#20). ⚠️ Each is mapped to TWO slugs on purpose. The Zid variant
         *   flattening left genuine duplicates of this item — two cartons
         *   (RTB-0005 at 120.00 and RTB-0043 at 69.00) and two singles (RTB-0004
         *   at 5.00 and RTB-0044 at 5.75) — so the photo goes to both members of
         *   each pair and whichever row survives a catalogue cleanup has it.
         *   Those duplicates still need merging; that is a data decision, not a
         *   photo one.
         */
        'خلاص-أشيقر-درجة-أولى-250-جرام-الكرتون' => [['retab-18'], []],
        'خلاص-أشيقر-درجة-آولى-وزن-250-جرام-كرتون' => [['retab-18'], []],
        'خلاص-أشيقر-درجة-أولى-250-جرام-الحبة' => [['retab-20'], []],
        'خلاص-أشيقر-درجة-آولى-وزن-250-جرام-حبة' => [['retab-20'], []],

        /*
         * ⚠️ This REPLACES the six rusk photos imported for Al Rashaqa in the
         * `rusks` group above, leaving one. Approved on 2026-08-17 on the basis
         * that the studio shot of the actual retail box beats six generic rusk
         * shots, and more angles will be supplied later.
         */
        'شابورة-بالقمح' => [['retab-19'], []],   // شابورة أمل الرشاقة box + tea glass

        // — Products that did not exist until the shoot; created as drafts by
        //   migration 2026_08_17_100000_add_photoshoot_products. —
        'تمر-خلاصي-بسبوسة' => [['retab-05'], []],
        'كيكة-الزعفران-بالكريمة' => [['retab-07'], []],
        'كيكة-دخن' => [['retab-08'], []],        // name read off the pack's label
        'دخيني-درجة-أولى-250-جرام' => [['retab-21'], []],
    ],
];
