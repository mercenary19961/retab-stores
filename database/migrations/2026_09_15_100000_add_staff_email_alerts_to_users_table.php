<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a staff account receives the alert EMAILS (new order, cancellation,
     * expiring payment, return, product request, contact message). The in-panel
     * bell is unaffected and always shows everything.
     *
     * Defaults to TRUE so every existing account keeps exactly the behaviour it
     * had; an admin switches it off per account on the Staff page.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('staff_email_alerts')->default(true)->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('staff_email_alerts');
        });
    }
};
