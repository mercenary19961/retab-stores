<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedupe timestamp for the post-delivery "write a review, get a discount"
 * WhatsApp reminder, so a repeated OTO "delivered" webhook can't re-send it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('review_reminder_sent_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('review_reminder_sent_at');
        });
    }
};
