<?php

namespace App\Support;

use App\Models\EventHeroBanner;
use App\Models\HeroSlide;
use App\Models\ProductImage;
use App\Models\StoreEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The undo window for deleted uploads.
 *
 * 🔑 WHY THIS EXISTS. Every admin delete used to call Media::delete() inline and
 * then drop the row, so the moment a client removed a hero slide its video and
 * artwork were gone from R2. Recording the delete in the change log would not
 * have helped: a revert needs the bytes, and the bytes were already destroyed.
 * Nothing here destroys a file at the moment a client asks for it to go; it is
 * detached, and `media:purge-trash` removes it once the window has closed.
 *
 * Two ways a file stops being used, and they need different handling:
 *
 *  1. THE RECORD IS TRASHED (a hero slide, an event banner, a product image).
 *     The row still holds its paths, so there is nothing to record — {@see files}
 *     reads them back off the trashed row at purge time.
 *  2. THE FILE IS DETACHED WHILE ITS RECORD LIVES ON — an upload replaced by a
 *     new one, or an event offer detached, whose banner sits on a pivot row that
 *     cannot be soft-deleted at all. Nothing holds the path any more, so it is
 *     written to `media_trash` by {@see schedule}.
 *
 * 🔴 isReferenced() IS THE SAFETY NET AND IT GUARDS BOTH. Before any file is
 * deleted, every media column in the schema is checked for that path — including
 * the columns of TRASHED rows, because a trashed row inside its window is exactly
 * what a revert is about to restore. So a hero image the client removed and then
 * restored from the change log is dropped from the queue instead of destroyed,
 * and no call site has to remember to un-schedule anything.
 */
class MediaTrash
{
    /**
     * Models whose trashed rows own files, in the order they are purged.
     *
     * ⚠️ StoreEvent is LAST on purpose: purging it force-deletes its banner rows
     * through the foreign key, so a banner trashed on its own is handled on its
     * own terms first rather than swept up as collateral.
     *
     * @var list<class-string<Model>>
     */
    public const OWNERS = [
        HeroSlide::class,
        EventHeroBanner::class,
        ProductImage::class,
        StoreEvent::class,
    ];

    /**
     * EVERY column in the schema that stores a media path, as table => columns.
     *
     * 🔴 A column missing from this map is a file that can be deleted while a live
     * record still points at it — the worst failure this class has, and it would
     * surface as an image that silently 404s weeks after anyone touched it.
     * Whenever a table gains an upload column it must be added here, and there is
     * a test that walks the real schema looking for likely media columns this map
     * has not heard of.
     *
     * ⚠️ Raw TABLES, not models, for two reasons: `event_product` is a pivot with
     * no model at all, and querying the table directly sees soft-deleted rows
     * without anyone having to remember `withTrashed()`.
     *
     * @var array<string, list<string>>
     */
    private const REFERENCES = [
        'hero_slides' => ['image', 'image_mobile', 'video', 'video_poster'],
        'event_hero_banners' => ['image', 'image_mobile'],
        'product_images' => ['path'],
        'event_product' => ['banner_image'],
        'categories' => ['image'],
    ];

    /**
     * JSON columns holding a LIST of paths, as table => column.
     *
     * Return photos are never deleted from the panel, so a path of theirs should
     * never reach the queue — they are checked anyway because the cost of being
     * wrong here is a customer's evidence photo disappearing from a dispute.
     *
     * @var array<string, string>
     */
    private const JSON_REFERENCES = [
        'order_returns' => 'photos',
    ];

    // ── Detaching ───────────────────────────────────────────────────────────

    /**
     * Queue a file whose record still exists (a replaced upload, a detached pivot).
     *
     * Safe to call with null or an empty string, so callers can hand over an
     * optional column without guarding it. Re-scheduling an already-queued path
     * restarts its clock rather than failing on the unique index — the file was
     * detached again, so the window should run from the most recent time.
     */
    public static function schedule(?string $path, string $context = '', ?Carbon $at = null): void
    {
        if (blank($path)) {
            return;
        }

        DB::table('media_trash')->updateOrInsert(
            ['path' => $path],
            [
                'context' => $context !== '' ? mb_substr($context, 0, 120) : null,
                // $at backdates the clock to when the thing holding the file was
                // itself trashed, so a purged record's artwork does not serve a
                // second full window on top of the one the record already served.
                'trashed_at' => $at ?? Carbon::now(),
            ],
        );
    }

    /**
     * Every stored path this record owns, including the ones on rows that go with
     * it.
     *
     * 🔴 A path this forgets is an orphan in R2 that nothing will ever reach.
     *
     * @return list<string>
     */
    public static function files(Model $record): array
    {
        $paths = match (true) {
            $record instanceof HeroSlide => [
                $record->image, $record->image_mobile, $record->video, $record->video_poster,
            ],
            $record instanceof EventHeroBanner => [
                $record->image, $record->image_mobile,
            ],
            $record instanceof ProductImage => [
                $record->path,
            ],
            // 🔑 An event owns nothing itself; its artwork sits on rows the foreign
            // key will take with it. Collect those here or force-deleting the event
            // strands every one of them in R2.
            $record instanceof StoreEvent => [
                ...$record->heroBanners()->withTrashed()->pluck('image')->all(),
                ...$record->heroBanners()->withTrashed()->pluck('image_mobile')->all(),
                ...DB::table('event_product')->where('store_event_id', $record->getKey())
                    ->pluck('banner_image')->all(),
            ],
            default => [],
        };

        return array_values(array_unique(array_filter($paths, fn ($p) => filled($p))));
    }

    // ── Purging ─────────────────────────────────────────────────────────────

    /** The cut-off: anything detached before this is past its undo window. */
    public static function cutoff(): Carbon
    {
        return Carbon::now()->subDays(max(0, (int) config('media.trash_retention_days', 30)));
    }

    /**
     * Is any record — live OR trashed — still pointing at this path?
     *
     * Trashed rows count, deliberately: one inside its window is precisely what a
     * revert is about to bring back, and deleting its file would restore a record
     * that renders nothing.
     */
    public static function isReferenced(string $path): bool
    {
        foreach (self::REFERENCES as $table => $columns) {
            $exists = DB::table($table)
                ->where(function ($q) use ($columns, $path) {
                    foreach ($columns as $column) {
                        $q->orWhere($column, $path);
                    }
                })
                ->exists();

            if ($exists) {
                return true;
            }
        }

        foreach (self::JSON_REFERENCES as $table => $column) {
            // The column is a JSON array of strings, so the path appears quoted.
            // ⚠️ LIKE rather than a JSON function: this has to behave identically
            // on MySQL 8, MariaDB 10.4 and the SQLite the tests run on.
            $escaped = addcslashes($path, '%_\\');
            if (DB::table($table)->where($column, 'like', '%"'.$escaped.'"%')->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Close the window: remove the files of records trashed past the cut-off,
     * force-delete those records, and flush the detached-file queue.
     *
     * ⚠️ The record is force-deleted BEFORE its files are considered, so that the
     * record itself does not count as a reference to its own paths. A crash
     * between the two leaves files with nothing pointing at them, which the next
     * run cannot see — so the paths are queued first, and the queue is what
     * actually drives every delete.
     *
     * Each record and each path is independent: a failure is logged and skipped
     * rather than abandoning the sweep.
     *
     * @return array{records: int, files: int, kept: int, failed: int}
     */
    public static function purge(bool $dryRun = false): array
    {
        $cutoff = self::cutoff();
        $records = 0;
        $failed = 0;

        /**
         * Paths step 1 hands to step 2. On a real run they are also written to
         * `media_trash`; on a dry run nothing is written, so they are carried
         * here instead — otherwise a dry run would report every record it is
         * about to purge and none of the files that go with it, which is the one
         * number the operator is checking before they widen the window.
         *
         * @var list<string>
         */
        $handedOver = [];

        // 1. Trashed records past the window: queue their files, then drop the row.
        foreach (self::OWNERS as $class) {
            $query = $class::onlyTrashed()->where('deleted_at', '<', $cutoff);

            foreach ($query->cursor() as $record) {
                try {
                    $paths = self::files($record);

                    if (! $dryRun) {
                        foreach ($paths as $path) {
                            self::schedule(
                                $path,
                                class_basename($record).'#'.$record->getKey(),
                                $record->deleted_at,
                            );
                        }

                        // 🔑 Force-deleted BEFORE the files are considered, so the
                        // record cannot count as a reference to its own paths when
                        // isReferenced() runs in step 2.
                        $record->forceDelete();
                    }

                    $handedOver = [...$handedOver, ...$paths];
                    $records++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('Failed to purge trashed record', [
                        'model' => $class,
                        'id' => $record->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // 2. The detached-file queue — every delete in the app goes through here.
        $files = 0;
        $kept = 0;

        $paths = DB::table('media_trash')->where('trashed_at', '<', $cutoff)->pluck('path')->all();
        if ($dryRun) {
            $paths = array_unique([...$paths, ...$handedOver]);
        }

        foreach ($paths as $path) {
            try {
                // ⚠️ On a dry run the records of step 1 are still in place, so
                // their own paths would read as referenced. They are not: the
                // record is about to go, which is why they bypass the check.
                $stillUsed = ! ($dryRun && in_array($path, $handedOver, true))
                    && self::isReferenced($path);

                if ($stillUsed) {
                    // Something points at it again — almost always a change-log
                    // revert. Drop it from the queue and leave the file alone.
                    $kept++;
                    if (! $dryRun) {
                        DB::table('media_trash')->where('path', $path)->delete();
                    }

                    continue;
                }

                if (! $dryRun) {
                    Media::delete($path); // removes the WebP variants too
                    DB::table('media_trash')->where('path', $path)->delete();
                }

                $files++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Failed to purge trashed file', ['path' => $path, 'error' => $e->getMessage()]);
            }
        }

        return ['records' => $records, 'files' => $files, 'kept' => $kept, 'failed' => $failed];
    }
}
