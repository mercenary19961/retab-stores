<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a removed staff member's email and phone be used again.
 *
 * 🔑 WHY STAFF REMOVAL BECAME A SOFT DELETE. `users` has carried the SoftDeletes
 * trait since the OTP identity model shipped, but Admin\UserController::destroy
 * called forceDelete() straight through it — and `activity_logs.user_id` is
 * ON DELETE SET NULL. So removing a colleague silently ANONYMISED every change
 * they had ever made: months of "who changed the price?" answered by a blank.
 * The row stays now, so the history keeps its author.
 *
 * 🔴 That makes these two indexes a problem, exactly as it did for coupon codes:
 * a trashed row goes on occupying `users_email_unique`, so re-hiring someone —
 * or simply undoing a mistaken removal by re-creating them — passes validation
 * and then dies on the database constraint with a 500 naming nothing.
 *
 * `(email, deleted_at)` works because NULLs are DISTINCT in a unique index on
 * MySQL, MariaDB and SQLite alike: one live row per address, any number of
 * trashed ones. Both columns are themselves nullable (a customer may have only a
 * phone, or only a Google account), which that behaviour already allowed and
 * still does.
 *
 * ⚠️ Restoring a trashed user whose email has since been given to someone else
 * fails on this constraint, which is correct — two live accounts must never share
 * a login. Staff removal is audit-only in the change log precisely so that is a
 * deliberate act rather than a one-click Undo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->dropUnique('users_phone_unique');
            $table->unique(['email', 'deleted_at']);
            $table->unique(['phone', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email', 'deleted_at']);
            $table->dropUnique(['phone', 'deleted_at']);
            $table->unique('email', 'users_email_unique');
            $table->unique('phone', 'users_phone_unique');
        });
    }
};
