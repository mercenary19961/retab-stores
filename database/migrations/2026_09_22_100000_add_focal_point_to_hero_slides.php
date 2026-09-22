<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the hero crop should centre, as a percentage of the artwork.
 *
 * 🔴 WHY: the hero is a fixed 2:1 band and the art is `object-cover`, so anything
 * that is not 2:1 gets CROPPED — and the crop is centred, which is exactly wrong
 * for a photo whose subject sits low or off to one side. The client uploaded an
 * image, saw it cut, and had no way to say which part mattered. This is that
 * control: click the part of the picture that must survive.
 *
 * Deliberately a FOCAL POINT rather than a crop rectangle. A crop tool means
 * storing a box, re-deriving it per breakpoint (the phone art is 4:5, the desktop
 * band 2:1) and writing a real editor; a focal point is two numbers that feed
 * CSS `object-position` and work at every size for free.
 *
 * ⚠️ Applies to the DESKTOP art and to video. Phone art is supplied already cut
 * to 4:5 by whoever made it, so it needs no second opinion about its centre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            // 0-100, defaulting to dead centre, which is what `object-cover` does
            // on its own — so existing slides are completely unaffected.
            $table->unsignedTinyInteger('focal_x')->default(50)->after('video_poster');
            $table->unsignedTinyInteger('focal_y')->default(50)->after('focal_x');
        });
    }

    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn(['focal_x', 'focal_y']);
        });
    }
};
