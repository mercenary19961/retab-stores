<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a deleted coupon's code be used again.
 *
 * 🔴 THE BUG THIS FIXES IS A 500, NOT A VALIDATION MESSAGE. Once coupons started
 * soft-deleting, the trashed row kept occupying `coupons_code_unique` — so
 * creating RAMADAN again after deleting RAMADAN passed validation (the rule is
 * scoped to live rows) and then died on the database constraint. The client sees
 * a server error, on a form they filled in correctly, naming nothing.
 *
 * 🔑 `(code, deleted_at)` is the standard soft-delete pattern and it works
 * because NULLs are DISTINCT in a unique index on MySQL, MariaDB and SQLite
 * alike: every live row has `deleted_at = NULL`, so only one of them may hold a
 * given code, while any number of trashed rows may — their timestamps differ.
 *
 * ⚠️ Two coupons with the same code deleted in the SAME second would still
 * collide. Accepted: it needs two people deleting two coupons that already share
 * a code, which the live-row constraint has always prevented in the first place.
 *
 * Coupons are the only newly soft-deleting table with a unique index; the other
 * eight were checked and have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropUnique('coupons_code_unique');
            $table->unique(['code', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropUnique(['code', 'deleted_at']);
            $table->unique('code', 'coupons_code_unique');
        });
    }
};
