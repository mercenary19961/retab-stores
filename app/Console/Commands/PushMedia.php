<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Support\ImageVariants;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copies stored media from one disk to another (dev "public" disk -> Cloudflare R2)
 * so production serves images from durable object storage instead of the container
 * filesystem, which Railway wipes on every deploy.
 *
 * Object KEYS are preserved exactly, which is what makes this a pure file copy:
 * product_images.path rows already hold these paths, so NO database change is
 * needed on either side. Safe to re-run — objects already on the target are
 * skipped unless --force, so an interrupted transfer simply resumes.
 *
 *   php artisan media:push --dry-run     # preview (no creds needed)
 *   php artisan media:push               # local public disk -> r2
 */
class PushMedia extends Command
{
    protected $signature = 'media:push
        {--from=public : Source disk to read from}
        {--to=r2 : Target disk to write to}
        {--force : Overwrite objects that already exist on the target}
        {--dry-run : Report what would be copied without writing anything}';

    protected $description = 'Copy stored media files to another disk (e.g. local public -> R2)';

    /**
     * Long-lived cache headers are safe because Media::storeImage() names every
     * file with a UUID — a given key's bytes never change, only new keys appear.
     */
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($from === $to) {
            $this->error("Source and target are the same disk [{$from}]. Nothing to do.");

            return self::FAILURE;
        }

        if ($error = $this->preflightError($to, $dryRun)) {
            $this->error($error);

            return self::FAILURE;
        }

        $source = Storage::disk($from);

        // Skip dotfiles — the local public disk carries Laravel's own .gitignore,
        // which has no business being served from a public bucket.
        $files = collect($source->allFiles())
            ->reject(fn (string $path) => str_starts_with(basename($path), '.'))
            ->sort()
            ->values();

        if ($files->isEmpty()) {
            $this->warn("No files found on the [{$from}] disk. Nothing to copy.");

            return self::SUCCESS;
        }

        $this->line("Copying <info>{$files->count()}</info> file(s) from [<info>{$from}</info>] to [<info>{$to}</info>]".($dryRun ? ' <comment>(dry run)</comment>' : ''));

        if ($dryRun) {
            return $this->reportDryRun($files);
        }

        return $this->copy($files, $from, $to, $force);
    }

    /**
     * Prove the target is genuinely writable before streaming 100s of MB at it.
     * A round-trip probe beats inspecting config values: a wrong key, a missing
     * bucket, or a read-only API token all look perfectly configured on paper and
     * would otherwise fail once per file.
     *
     * Skipped on a dry run so a transfer can be previewed before the bucket exists.
     */
    private function preflightError(string $disk, bool $dryRun): ?string
    {
        if (! is_array(config("filesystems.disks.{$disk}"))) {
            return "Unknown disk [{$disk}]. Check config/filesystems.php.";
        }

        if ($dryRun) {
            return null;
        }

        $hint = "\nCheck the R2_ACCESS_KEY_ID / R2_SECRET_ACCESS_KEY / R2_BUCKET / R2_ENDPOINT environment variables.";
        $probe = '.media-push-probe-'.uniqid();

        try {
            $target = Storage::disk($disk);

            // The r2 disk sets throw => false, so a failure surfaces as a falsy
            // return rather than an exception. Both paths have to be handled.
            if ($target->put($probe, 'ok') === false) {
                return "Cannot write to disk [{$disk}].".$hint;
            }

            $readBack = $target->get($probe);
            $target->delete($probe);

            if ($readBack !== 'ok') {
                return "Disk [{$disk}] accepted a write but read back different bytes.".$hint;
            }
        } catch (Throwable $e) {
            return "Cannot write to disk [{$disk}]: ".$e->getMessage().$hint;
        }

        return null;
    }

    /** @param Collection<int, string> $files */
    private function reportDryRun($files): int
    {
        foreach ($files->take(5) as $path) {
            $this->line("  {$path}");
        }

        if ($files->count() > 5) {
            $this->line('  ... and '.($files->count() - 5).' more');
        }

        $this->newLine();
        $this->info('Dry run only — nothing was written.');

        return self::SUCCESS;
    }

    /** @param Collection<int, string> $files */
    private function copy($files, string $from, string $to, bool $force): int
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        $copied = 0;
        $skipped = 0;
        $bytes = 0;
        $failures = [];

        $bar = $this->output->createProgressBar($files->count());
        $bar->start();

        foreach ($files as $path) {
            try {
                if (! $force && $target->exists($path)) {
                    $skipped++;

                    continue;
                }

                $stream = $source->readStream($path);
                if (! is_resource($stream)) {
                    $failures[$path] = 'could not open the source file';

                    continue;
                }

                try {
                    $target->writeStream($path, $stream, [
                        'ContentType' => $this->mimeFor($path),
                        'CacheControl' => self::CACHE_CONTROL,
                    ]);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                $copied++;
                $bytes += (int) $source->size($path);
            } catch (Throwable $e) {
                $failures[$path] = $e->getMessage();
            } finally {
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Copied %d file(s) (%s); skipped %d already present.',
            $copied,
            $this->humanBytes($bytes),
            $skipped,
        ));

        if ($failures !== []) {
            $this->newLine();
            $this->error(count($failures).' file(s) failed:');
            foreach (array_slice($failures, 0, 10, true) as $path => $message) {
                $this->line("  <comment>{$path}</comment>: {$message}");
            }
            $this->line('Re-run the command to retry only the missing files.');

            return self::FAILURE;
        }

        return $this->verify($target);
    }

    /**
     * The real question is not "did bytes move" but "will the storefront render".
     * Every product_images row must resolve on the target, along with the variants
     * the catalogue and product pages actually request.
     */
    private function verify(Filesystem $target): int
    {
        $paths = ProductImage::query()->pluck('path')->filter()->unique();

        if ($paths->isEmpty()) {
            return self::SUCCESS;
        }

        $variants = ImageVariants::enabled() ? ImageVariants::names() : [];
        $missingOriginals = 0;
        $missingVariants = 0;

        foreach ($paths as $path) {
            if (! $target->exists($path)) {
                $missingOriginals++;
            }

            foreach ($variants as $variant) {
                if (! $target->exists(ImageVariants::variantPath($path, $variant))) {
                    $missingVariants++;
                }
            }
        }

        $this->newLine();

        if ($missingOriginals === 0 && $missingVariants === 0) {
            $this->info("Verified: all {$paths->count()} product image(s) and their variants are present on the target disk.");

            return self::SUCCESS;
        }

        $this->error("Verification failed: {$missingOriginals} original(s) and {$missingVariants} variant(s) are missing on the target.");
        $this->line('Run <comment>php artisan media:variants</comment> locally to rebuild variants, then push again.');

        return self::FAILURE;
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1).' GB';
        }

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
