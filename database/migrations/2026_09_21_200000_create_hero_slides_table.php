<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The homepage hero, managed by the client at /admin/hero.
 *
 * 🔑 Deliberately a SEPARATE table from `event_hero_banners` rather than that
 * one's `store_event_id` made nullable. An event banner's whole design is that it
 * inherits its campaign's window and leaves the homepage with it, with nothing to
 * remember to switch off. A standalone slide has no campaign to inherit from, so
 * folding the two together would mean a model whose central rule holds for only
 * half its rows — and `EventHeroBanner::scopeLive()` inner-joins `store_events`
 * for ordering, which would silently drop every event-less row.
 *
 * Which of the two the storefront actually shows is the CLIENT'S choice, stored
 * in the `hero_event_mode` setting rather than hardcoded here. See HeroBanners.
 *
 * ⚠️ `dateTime`, NOT `timestamp`. MariaDB gives the first non-nullable TIMESTAMP
 * an implicit CURRENT_TIMESTAMP default and every later one '0000-00-00', which
 * strict mode rejects outright. Same trap the store_events and announcements
 * migrations both hit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();

            // 'image' or 'video'. Kept as a string rather than an enum column so
            // adding a third kind later is a code change, not a migration.
            $table->string('kind', 16)->default('image');

            // Desktop art (2:1). Required for an image slide, null for a video.
            $table->string('image')->nullable();
            // Optional portrait crop (4:5) for phones. See hero.tsx: phone art is
            // only used when EVERY slide has it, or the carousel changes height
            // on every rotation.
            $table->string('image_mobile')->nullable();

            // The video file itself, and the frame shown before it plays.
            $table->string('video')->nullable();
            // 🔑 Not optional in spirit even though it is nullable: it is what a
            // visitor sees while the video downloads, and what replaces the video
            // entirely under prefers-reduced-motion. A video slide without one
            // renders an empty box for the first second of every visit.
            $table->string('video_poster')->nullable();

            // Where the slide goes when clicked. Null → not a link.
            $table->string('href')->nullable();

            // The art carries its own headline, so this is what a screen reader
            // hears instead. Arabic required by the app's bilingual contract,
            // English optional and falling back to it.
            $table->string('alt_ar')->nullable();
            $table->string('alt_en')->nullable();

            // Both optional, and a NULL means "no bound on that side" — which is
            // what makes "run it until I hide it" work with no second flag.
            // Same rule as Announcement::scopeLive().
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // The storefront asks "what is live, in order" on every homepage load.
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
