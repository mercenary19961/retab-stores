<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store announcements — the strip above the storefront.
 *
 * Built for the client's real case: "stuffed dates ship to Riyadh only", which
 * the Zid store carries as a banner today. ⚠️ It ANNOUNCES, it does not enforce
 * — a Jeddah shopper can still order them, and the admin rejects that order at
 * the confirm step (client's explicit decision, 2026-09-21).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            // Same bilingual contract as the rest of the catalogue: Arabic is
            // required, English is optional and falls back to Arabic.
            $table->string('message_ar', 500);
            $table->string('message_en', 500)->nullable();

            // Optional call to action. Label falls back to a generic "Learn more"
            // string when a URL is given without one.
            $table->string('link_url')->nullable();
            $table->string('link_label_ar', 120)->nullable();
            $table->string('link_label_en', 120)->nullable();

            // info | warning | success. A shipping restriction and a free-delivery
            // promotion are not the same message and should not look the same.
            $table->string('tone', 20)->default('info');

            $table->boolean('is_active')->default(true);

            /*
             * The schedule. ⚠️ BOTH nullable, and that is the feature: a null
             * `ends_at` is the client's "leave it up until I take it down", while
             * a set one expires by itself so nobody has to remember.
             *
             * 🔴 dateTime, NOT timestamp. MariaDB gives the first non-nullable
             * TIMESTAMP an implicit CURRENT_TIMESTAMP default and every later one
             * '0000-00-00', which strict mode rejects outright. Same trap the
             * store_events migration hit and documented.
             */
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // The storefront asks "what is live right now?" on every page load.
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
