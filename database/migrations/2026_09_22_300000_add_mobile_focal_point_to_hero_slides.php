<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A SECOND focal point, for the phone artwork.
 *
 * 🔴 The first pass assumed phone art arrives already cut to 4:5 and therefore
 * needs no opinion about its centre. That is wrong in practice: what a client
 * has to hand is usually a social-media export (1080x1920, 9:16), which the 4:5
 * band still crops — and because it is a DIFFERENT file from the desktop art,
 * the desktop focal point is meaningless for it.
 *
 * So each surface now carries its own. Defaults to centre, which is what
 * `object-cover` already did, so nothing existing moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->unsignedTinyInteger('focal_mobile_x')->default(50)->after('focal_y');
            $table->unsignedTinyInteger('focal_mobile_y')->default(50)->after('focal_mobile_x');
        });
    }

    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn(['focal_mobile_x', 'focal_mobile_y']);
        });
    }
};
