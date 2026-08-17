<?php

/**
 * Named maps for `php artisan catalog:import-images <group> --dir=...`, used to
 * attach better client-supplied product photos, one category at a time.
 *
 * Each entry: product SKU (or slug) => [ include-all, exclude-any ] — lists of
 * substrings matched (Arabic-aware) against image FILENAMES. A file is assigned to
 * a product when it contains EVERY "include" substring and NONE of the "exclude"
 * ones. The same file set can map to more than one product (e.g. two pack sizes of
 * one rusk, or the كرتون/حبة pair of one dates line).
 *
 * 🔑 KEY NEW GROUPS ON SKU, NOT SLUG. This command has to run on production, and
 * slugs differ between environments: `catalog:clean-slugs --apply` has run locally
 * but is deferred to launch on prod, so 27 products have clean Arabic slugs here
 * and junk ones there (`قهوة-نجدية` locally is `Najdi-coffee` on prod). A
 * slug-keyed map silently skips those products on prod. SKUs are identical in both
 * (verified: اقط is RTB-0047 in each). The `rusks` group below predates this and is
 * left on slugs, all of which happen to match on prod.
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
     * Keyed on SKU, not slug — see the file header for why that is load-bearing.
     *
     * ⚠️ Filenames are zero-padded, which is what makes a bare 'retab-12' an
     * unambiguous matcher — without the padding it would also catch retab-1.
     *
     * ⚠️ The importer REPLACES a product's existing images, so every SKU listed
     * here loses its old Zid photos. That is the intent (these are far better),
     * but it is destructive: check the product's current image count first.
     */
    'photoshoot-2026-08' => [
        // — Pantry / non-date lines, each identified from its own printed label —
        'RTB-0047' => [['retab-01'], []],   // اقط — Retab tub + two discs
        'RTB-0053' => [['retab-02'], []],   // دبس رطاب — Retab-branded دبس التمر bottle
        'RTB-0018' => [['retab-03'], []],   // طحينة عصار — bottle reads طحينة فاخرة / Premium Tahina
        'RTB-0027' => [['retab-04'], []],   // قهوة نجدية — teal jar + dallah
        'RTB-0025' => [['retab-06'], []],   // بوكس مكسرات مشكل — hex box, LUXURY NUTS
        'RTB-0013' => [['retab-10'], []],   // مقلوبة التمر — box + tub + plated slice
        'RTB-0052' => [['retab-11'], []],   // تمرية رطاب — red نمرية box + sesame-ball tub
        'RTB-0022' => [['retab-15'], []],   // بوكس التمرة الذهبية — cellophane bag of wrapped pieces
        'RTB-0057' => [['retab-17'], []],   // سكري يدوي مجروش فاخر — pressed block

        // — Stuffed-date boxes, matched on BOX COLOUR against the live storefront
        //   photos: #12 is the purple box, the rest are white. —
        'RTB-0050' => [['retab-12'], []],   // بوكس سكري محشي لوز — purple box
        'RTB-0049' => [['retab-13'], []],   // بوكس سكري محشي بالكريمة — white, mixed toppings
        'RTB-0019' => [['retab-14'], []],   // بوكس تمر محشي مشكل — white, pistachio + walnut
        'RTB-0016' => [['retab-16'], []],   // صينية تمر محشي — large tray, pistachio + almonds

        // — Dates, each read straight off the pack's own printed name —
        'RTB-0082' => [['retab-22'], []],   // صقعي فاخر — SAQI DATES 1kg
        'RTB-0042' => [['retab-23'], []],   // عجوة المدينة — AJWA DATES 1kg
        // One photo, two SKUs: every dates line exists as a كرتون/حبة pair of the
        // same physical product, so both share the shot.
        'RTB-0055' => [['retab-24'], []],   // سكري فاخر ١ كيلو كرتون — SUGAR DATES 1kg
        'RTB-0056' => [['retab-24'], []],   // سكري فاخر ١ كيلو حبة

        // — قدوع. The tin's label reads only "قدوع", never the flavour, so the
        //   client asked for one shot on all three until per-flavour photos exist. —
        'RTB-0058' => [['retab-09'], []],   // قدوع رطاب سكري
        'RTB-0059' => [['retab-09'], []],   // قدوع رطاب خلاص
        'RTB-0024' => [['retab-09'], []],   // قدوع رطاب بالكراميل المملح

        /*
         * — 250g Khalas Ushaiger: the display CARTON (#18) and a single vacuum
         *   PACK (#20). ⚠️ Each is mapped to TWO SKUs on purpose. The Zid variant
         *   flattening left genuine duplicates of this item — two cartons
         *   (RTB-0005 at 120.00 and RTB-0043 at 69.00) and two singles (RTB-0004
         *   at 5.00 and RTB-0044 at 5.75) — so the photo goes to both members of
         *   each pair and whichever row survives a catalogue cleanup has it.
         *   Those duplicates still need merging; a pricing decision, not a photo one.
         */
        'RTB-0005' => [['retab-18'], []],   // …250 جرام - الكرتون  (120.00)
        'RTB-0043' => [['retab-18'], []],   // …وزن 250 جرام - كرتون (69.00)
        'RTB-0004' => [['retab-20'], []],   // …250 جرام - الحبة    (5.00)
        'RTB-0044' => [['retab-20'], []],   // …وزن 250 جرام - حبة   (5.75)

        /*
         * ⚠️ This REPLACES the six rusk photos imported for Al Rashaqa in the
         * `rusks` group above, leaving one. Approved on 2026-08-17 on the basis
         * that the studio shot of the actual retail box beats six generic rusk
         * shots, and more angles will be supplied later.
         */
        'RTB-0064' => [['retab-19'], []],   // شابورة أمل الرشاقة — box + tea glass

        // — Products that did not exist until the shoot; created as drafts by
        //   migration 2026_08_17_100000_add_photoshoot_products. —
        'RTB-0088' => [['retab-05'], []],   // تمر خلاصي بسبوسة
        'RTB-0089' => [['retab-07'], []],   // كيكة الزعفران بالكريمة
        'RTB-0090' => [['retab-08'], []],   // كيكة دخن — name read off the pack's label
        'RTB-0091' => [['retab-21'], []],   // دخيني درجة أولى 250 جرام
    ],
];
