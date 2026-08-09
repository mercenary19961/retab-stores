<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Initial back-office admin
    |--------------------------------------------------------------------------
    |
    | Created by AdminUserSeeder. In production set ADMIN_EMAIL + ADMIN_PASSWORD
    | in the environment; the seeder refuses to create a weak default admin when
    | APP_ENV=production. Read through config (never env() directly in the
    | seeder) so the values survive `php artisan config:cache` — set them BEFORE
    | the deploy's optimize step, or config:clear afterwards.
    |
    */
    // `?:` (not env()'s 2nd arg) so a present-but-EMPTY var — e.g. the copied
    // `.env` in CI with `ADMIN_EMAIL=` — falls back too, not just an absent one.
    'admin' => [
        'email' => env('ADMIN_EMAIL') ?: 'admin@retab.com.sa',
        'password' => env('ADMIN_PASSWORD') ?: null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search-engine indexing
    |--------------------------------------------------------------------------
    |
    | Set SITE_INDEXABLE=false to keep the store out of Google while it is still
    | reachable (Moyasar's application review, client walkthroughs, the pre-
    | launch domain). Every response then carries X-Robots-Tag: noindex.
    |
    | Defaults to TRUE on purpose: a forgotten variable that leaves the live
    | store deindexed is a far worse failure than one that indexes a staging
    | URL, so this must be opted OUT of explicitly. 🔴 Flip it back at launch.
    |
    | filter_var (not a bare env()) so SITE_INDEXABLE=0 / "no" / "off" are all
    | honoured — a typo here silently changes whether the store is in Google.
    |
    */
    'indexable' => filter_var(env('SITE_INDEXABLE', true), FILTER_VALIDATE_BOOLEAN),
];
