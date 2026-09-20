<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avatar URLs need more than the default VARCHAR(255).
 *
 * 🔴 This is not a precaution. The first real Google sign-in on production died
 * with SQLSTATE[22001] "Data too long for column 'avatar'": Google now issues
 * `lh3.googleusercontent.com/a-/ALV-Uj…` URLs carrying a long opaque token, well
 * past 255 characters. The whole callback is wrapped in a transaction, so the
 * account was rolled back and the customer got a 500 instead of an account.
 *
 * 1024 rather than TEXT: it is still a plain VARCHAR (no off-page storage, no
 * default/index quirks), and it is four times the longest URL any provider has
 * been observed to send. GoogleAuthController::AVATAR_MAX matches it, and a test
 * pins the two together so widening one without the other cannot go unnoticed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ⚠️ change() REPLACES the column definition, so nullable() has to be
        // restated — dropping it here would make every avatar-less account fail.
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar', 1024)->nullable()->change();
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('avatar', 1024)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Narrowing truncates, so clear anything that would not survive the trip
        // back rather than letting MySQL silently cut a URL into a broken one.
        DB::table('users')->whereRaw('LENGTH(avatar) > 255')->update(['avatar' => null]);
        DB::table('social_accounts')->whereRaw('LENGTH(avatar) > 255')->update(['avatar' => null]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->change();
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('avatar')->nullable()->change();
        });
    }
};
