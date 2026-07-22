<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Support\ImageVariants;
use App\Support\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Backfills responsive WebP variants for product images that predate the pipeline
 * (new uploads generate them automatically via Media::storeImage). Idempotent:
 * skips images whose variants already exist unless --force is given.
 */
class GenerateImageVariants extends Command
{
    protected $signature = 'media:variants {--force : Regenerate even when a variant already exists}';

    protected $description = 'Generate responsive WebP variants for all stored product images';

    public function handle(): int
    {
        if (! ImageVariants::enabled()) {
            $this->warn('Image variants are disabled (config media.variants_enabled). Nothing to do.');

            return self::SUCCESS;
        }

        $paths = ProductImage::query()->pluck('path')->filter()->unique()->values();
        if ($paths->isEmpty()) {
            $this->info('No product images to process.');

            return self::SUCCESS;
        }

        $disk = Storage::disk(Media::disk());
        $force = (bool) $this->option('force');
        $done = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($paths->count());
        $bar->start();

        foreach ($paths as $path) {
            // A present 'card' variant means this image is already done.
            if (! $force && $disk->exists(ImageVariants::variantPath($path, 'card'))) {
                $skipped++;
                $bar->advance();

                continue;
            }

            ImageVariants::generate($path);
            $done++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Generated variants for {$done} image(s); skipped {$skipped} already done.");

        return self::SUCCESS;
    }
}
