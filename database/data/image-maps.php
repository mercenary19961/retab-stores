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
];
