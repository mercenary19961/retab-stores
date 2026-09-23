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
    | Store owner
    |--------------------------------------------------------------------------
    |
    | The ONE account allowed to change who is an admin (promote, demote, create
    | an admin), and whose password no other admin can reset. Everyone else with
    | the admin role still runs the store, but cannot hand out or take away admin
    | access. See User::isOwner() and Admin\UserController.
    |
    | Defaults to the initial admin above, so a fresh install needs nothing set.
    |
    */
    'owner_email' => env('OWNER_EMAIL') ?: (env('ADMIN_EMAIL') ?: 'admin@retab.com.sa'),

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

    /*
    |--------------------------------------------------------------------------
    | Require an email address at checkout
    |--------------------------------------------------------------------------
    |
    | 🔴 Email is currently the ONLY channel that reaches a customer. WhatsApp is
    | built and wired but not configured in production (no token, no registered
    | number), so every WhatsApp message resolves to the log driver and is
    | written to a file. With `customer_email` optional — which it is by design,
    | because the identity model allows a phone-only account — a customer who
    | checks out without one receives NOTHING: no receipt, no confirmation, no
    | tracking, no word when their order cannot be filled and they are refunded.
    |
    | Requiring an address closes that hole without touching the identity model:
    | an account can still exist with only a phone, it just cannot place an order
    | we have no way of talking to.
    |
    | ⚠️ This is a STOPGAP, and the flag exists to make undoing it one variable
    | rather than a code change. Once WhatsApp is live, set
    | REQUIRE_CUSTOMER_EMAIL=false to restore phone-only checkout — or leave it
    | on, if email turns out to be the better receipt channel anyway.
    |
    | filter_var for the same reason as `indexable`: "0" / "no" / "off" must all
    | work, because a typo here decides whether a customer can be reached at all.
    |
    */
    'require_customer_email' => filter_var(env('REQUIRE_CUSTOMER_EMAIL', true), FILTER_VALIDATE_BOOLEAN),
];
