<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Per-editor granular permissions: { section: { action: bool } }.
            // Null => the editor inherits Permission::DEFAULTS. Admins bypass entirely.
            $table->json('permissions')->nullable()->after('confirmed_purchases_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
