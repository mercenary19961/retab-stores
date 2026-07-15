<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change-log v2 (see CLAUDE.md → Change Log): split before/after snapshots
        // (dirty fields only on updates), a human label for the admin list, and a
        // self-link marking entries produced by reverting another entry — which
        // makes reverts first-class history and gives redo for free. The legacy
        // `changes` JSON stays for bespoke payloads (SMACC stock import).
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->json('old_data')->nullable()->after('changes');
            $table->json('new_data')->nullable()->after('old_data');
            $table->string('label')->nullable()->after('new_data');
            $table->foreignId('reverts_log_id')->nullable()->after('label')
                ->constrained('activity_logs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reverts_log_id');
            $table->dropColumn(['old_data', 'new_data', 'label']);
        });
    }
};
