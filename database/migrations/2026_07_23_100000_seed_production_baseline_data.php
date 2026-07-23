<?php

use Database\Seeders\ContentPageSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed the secret-free baseline rows every deployed environment needs: store
 * settings + the baseline CMS/legal pages. Railpack runs `migrate --force` on
 * each deploy but never `db:seed`, so without this a fresh production DB would
 * boot with no settings and no legal pages (Moyasar activation needs a live
 * returns/refund policy page). Both seeders are idempotent (Setting::set /
 * firstOrCreate), so re-running on every deploy is safe and non-destructive.
 *
 * The admin user is deliberately NOT seeded here (it carries a secret + would
 * hit the run-once trap): it's created from the ADMIN_* env vars via
 * AdminUserSeeder, run once from the deploy console. Skipped under `testing`
 * so the suite keeps its clean, empty baseline.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        (new SettingsSeeder)->run();
        (new ContentPageSeeder)->run();
    }

    public function down(): void
    {
        // Baseline reference data — intentionally not rolled back.
    }
};
