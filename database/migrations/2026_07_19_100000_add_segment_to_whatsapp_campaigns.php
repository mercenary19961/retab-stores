<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            // Which audience segment this campaign targeted (all|active|recent|
            // repeat|dormant). Stored so the queued job re-runs the same segment.
            $table->string('segment', 20)->default('all')->after('params');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropColumn('segment');
        });
    }
};
