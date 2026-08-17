<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stamp for the Tamara authorisation-expiry alert.
 *
 * Without it the hourly command would re-alert every single run for the whole
 * window, which trains staff to ignore the one notification that costs real
 * money. Mirrors `orders.review_reminder_sent_at`, which exists for the same
 * reason on the review nudge.
 *
 * Named for the CONCEPT rather than the vendor: any authorise-then-capture
 * gateway has the same expiry problem, and Tamara is simply the only one we
 * use today (cards capture immediately).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_expiry_alerted_at')->nullable()->after('review_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_expiry_alerted_at');
        });
    }
};
