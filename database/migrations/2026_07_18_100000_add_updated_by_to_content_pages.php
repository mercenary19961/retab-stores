<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_pages', function (Blueprint $table) {
            // Who last saved this page — shown in the admin CMS list/editor.
            $table->foreignId('updated_by')->nullable()->after('is_published')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
