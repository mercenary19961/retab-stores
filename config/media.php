<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Responsive image variants
    |--------------------------------------------------------------------------
    |
    | Every image stored through App\Support\Media gets these variants generated
    | as WebP alongside the original. Each entry is a max WIDTH (height auto,
    | aspect ratio preserved) plus a WebP quality. Widths only DOWNSCALE — an
    | image already narrower than the target is left untouched, never enlarged.
    |
    | Serve one with Media::url($path, 'card'); omit the variant for the original
    | (e.g. a "view full size / zoom" action on the product page).
    |
    */

    'variants' => [
        'thumb' => ['width' => 150, 'quality' => 80],   // search suggestions, cart lines
        'card' => ['width' => 500, 'quality' => 80],    // catalogue / homepage grid cards
        'detail' => ['width' => 1400, 'quality' => 82], // product page main image
        // Homepage hero banners, which run full-bleed on 1920px screens and carry
        // baked-in text — `detail` would be upscaled 1.37× there and the type goes
        // soft. Generated for every upload (products too) because variants are
        // global; the cost is one extra WebP per image, and only banners request it.
        'hero' => ['width' => 1920, 'quality' => 84],
    ],

    /*
    | Master switch. When off, Media::url() always returns the ORIGINAL — a safe
    | fallback for an environment without GD/Imagick WebP support, or mid-migration
    | before the backfill (php artisan media:variants) has run.
    */
    'variants_enabled' => env('MEDIA_VARIANTS', true),

    /*
    | intervention/image driver: 'gd' (default, ubiquitous, has WebP when the PHP
    | GD extension is built with it) or 'imagick'. Production images must ship the
    | chosen extension WITH WebP support (Railpack/FrankenPHP PHP includes GD+WebP).
    */
    'image_driver' => env('MEDIA_IMAGE_DRIVER', 'gd'),

    /*
    | Hero video size cap, in megabytes. Enforced in Media::storeVideo() AND in
    | the admin form's validation, so a file that slips past one meets the other.
    |
    | Deliberately small. The hero video is downloaded by every first-time visitor
    | before they reach a single product, on a store whose customers are mostly on
    | phones - and the client will judge it on office wifi, where a 60 MB file
    | feels fine. 12 MB is roughly 15 seconds of decent 1080p h.264.
    */
    'video_max_mb' => (int) env('MEDIA_VIDEO_MAX_MB', 12),

    /*
    |--------------------------------------------------------------------------
    | Trash retention
    |--------------------------------------------------------------------------
    |
    | How long a soft-deleted record keeps its uploaded files before
    | `php artisan media:purge-trash` removes them and force-deletes the row.
    |
    | 🔑 This number IS the undo window. Deleting a hero slide used to run
    | Media::delete() on its video and artwork immediately, so the bytes were gone
    | from R2 before anyone could ask for them back — a change-log entry could
    | record the loss but never reverse it. Nothing is destroyed inside the window,
    | so a revert always has something to restore.
    |
    | ⚠️ Raising it costs only R2 storage (fractions of a cent per GB-month).
    | LOWERING it destroys files that are currently restorable, so treat a
    | reduction as a destructive change and take a backup first.
    |
    */
    'trash_retention_days' => (int) env('MEDIA_TRASH_RETENTION_DAYS', 30),

];
