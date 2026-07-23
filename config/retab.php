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
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@retab.com.sa'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
