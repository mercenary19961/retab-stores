<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the language the customer actually checked out in.
 *
 * Customer emails are QUEUED, so by the time one renders, `app()->getLocale()`
 * is the worker's locale (AR, the app default) — not the customer's. A guest who
 * shopped the store in English would silently get an Arabic receipt. The order
 * row already snapshots name/email/phone/address for exactly this reason ("who
 * ordered"), so the language belongs with them; it also covers guests, who have
 * no user record to read a preference from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('locale', 5)->default('ar')->after('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
