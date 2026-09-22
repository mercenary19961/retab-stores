<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files that are no longer attached to anything, waiting out the undo window.
 *
 * 🔑 SOFT-DELETING THE ROW IS NOT ENOUGH ON ITS OWN. Two paths detach a file
 * while the record that held it lives on:
 *
 *   - REPLACING an upload. Editing a hero slide's artwork used to delete the old
 *     file the moment the new one stored, so reverting that edit wrote the old
 *     path back into a row whose file no longer existed — a slide pointing at a
 *     404, which renders nothing.
 *   - DETACHING an event offer, whose banner lives on the `event_product` PIVOT.
 *     A pivot row cannot be soft-deleted, so there is nothing to trash.
 *
 * Both now record the path here instead of destroying it, and `media:purge-trash`
 * removes it once the window has passed. The purge re-checks that nothing points
 * at the path before it deletes, so a file that a revert brought back is simply
 * dropped from this table rather than destroyed.
 *
 * ⚠️ `path` is unique: scheduling the same file twice must not queue two deletes,
 * and the second schedule should refresh the clock rather than fail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_trash', function (Blueprint $table) {
            $table->id();
            // 512 rather than the default 255: R2 keys nest a directory per
            // product or event under a UUID filename, and an index on a longer
            // string costs nothing at this volume.
            $table->string('path', 512)->unique();
            // What detached it ("hero_slides.video", "event_product.banner_image").
            // Debug-only, and deliberately not a foreign key — the whole point is
            // that the thing holding the file may be gone.
            $table->string('context', 120)->nullable();
            $table->timestamp('trashed_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_trash');
    }
};
