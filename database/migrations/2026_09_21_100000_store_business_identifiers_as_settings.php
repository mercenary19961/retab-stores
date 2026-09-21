<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Move the commercial registration and VAT number out of the code and into
 * `settings`, where they belong.
 *
 * 🔴 WHY THIS EXISTS, because it reads like tidying and is not. Both numbers are
 * printed in the public storefront footer, and the footer fell back to hardcoded
 * constants in SettingController::FOOTER_DEFAULTS whenever the stored value was
 * blank. So the owner-confirmed "delete the business identifiers" flow cleared
 * the settings rows and the storefront went on printing the numbers anyway — the
 * exact thing that feature promises not to do, with nothing to notice it. A
 * hardcoded fallback for a value the owner is allowed to delete is a
 * contradiction; the fallback is now empty and the values live here.
 *
 * ⚠️ Production has never had these rows AT ALL (the footer has been serving the
 * constants since it shipped), so without this backfill removing the fallback
 * would blank both badges on the live site. That is the whole reason a migration
 * is needed rather than just the seeder change: the baseline-data migration that
 * runs SettingsSeeder has already run there and will not run again.
 *
 * ⚠️ The values are written out in full rather than read from
 * SettingsSeeder::defaults(). A migration is a frozen record of what it did, and
 * reading a constant would let a later edit silently change the meaning of a
 * migration that has already run. (Same reasoning as the category-tile backfill.)
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const IDENTIFIERS = [
        'commercial_registration' => '7001744098',
        'vat_number' => '300789485500003',
    ];

    public function up(): void
    {
        // The suite starts from an empty settings table on purpose. Seeding real
        // identifiers into it would make the "deleted values stay deleted"
        // assertions pass for the wrong reason. Same guard as the baseline-data
        // migration.
        if (app()->environment('testing')) {
            return;
        }

        // Fills only what is absent or blank, so a re-run on a fresh database is
        // safe and an admin's correction can never be overwritten.
        foreach (self::IDENTIFIERS as $key => $value) {
            if (blank(Setting::get($key))) {
                Setting::set($key, $value);
            }
        }
    }

    public function down(): void
    {
        // Deliberately nothing. These are the business's own identifiers, not
        // this migration's to take away once they are in the settings table —
        // and deleting them on a rollback would blank the footer badges.
    }
};
