<?php

namespace App\Console\Commands;

use App\Support\MediaTrash;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Closes the undo window: removes the files owned by records trashed longer ago
 * than `media.trash_retention_days` and force-deletes those rows.
 *
 * 🔑 The point of the delay is the change log. An admin delete now soft-deletes
 * and leaves the artwork alone, so "Undo" can put a hero slide's video back;
 * without this command those files would simply accumulate in R2 forever. It is
 * therefore the ONLY thing in the app that destroys an upload, and the one place
 * to look when a file is unexpectedly missing.
 *
 * Scheduled daily (routes/console.php). Daily rather than hourly because the
 * window is measured in weeks — running it more often buys nothing and it walks
 * four tables.
 *
 * ⚠️ --dry-run reports exactly what a real run would remove and touches nothing.
 * Use it before any change to the retention setting, since shortening the window
 * makes currently-restorable files eligible on the very next run.
 */
class PurgeTrashedMedia extends Command
{
    protected $signature = 'media:purge-trash {--dry-run : Report what would be removed without removing it}';

    protected $description = 'Delete the uploads of records trashed past the retention window, and force-delete those records.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = MediaTrash::cutoff();
        $days = (int) config('media.trash_retention_days', 30);

        $this->line("Retention: {$days} days (purging records trashed before {$cutoff->toDateTimeString()})");

        $result = MediaTrash::purge($dryRun);

        if ($result['records'] === 0 && $result['files'] === 0 && $result['kept'] === 0) {
            $this->info('Nothing past the retention window.');

            return self::SUCCESS;
        }

        $verb = $dryRun ? 'Would purge' : 'Purged';
        $this->info("{$verb} {$result['records']} record(s) and {$result['files']} file(s).");

        if ($result['kept'] > 0) {
            // Restored from the change log while it sat in the queue, so the file
            // is in use again. Worth printing: it is the undo window doing its job.
            $this->line("Kept {$result['kept']} file(s) that are referenced again.");
        }

        if ($result['failed'] > 0) {
            // Not a failure exit: the rest of the sweep succeeded and the next run
            // retries these. The log carries the model and id.
            $this->warn("{$result['failed']} record(s) could not be purged — see the log.");
        }

        if (! $dryRun) {
            Log::info('Purged trashed media', $result + ['cutoff' => $cutoff->toDateTimeString()]);
        }

        return self::SUCCESS;
    }
}
