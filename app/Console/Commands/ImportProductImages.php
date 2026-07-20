<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalog\ProductImageImporter;
use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Attach better client-supplied product photos from a local folder, one category
 * group at a time, using the slug→filename maps in database/data/image-maps.php.
 * Replaces each matched product's existing images. Run with --dry-run first.
 */
class ImportProductImages extends Command
{
    protected $signature = 'catalog:import-images
        {group : Map key in database/data/image-maps.php (e.g. rusks)}
        {--dir= : Source folder of images (scanned recursively)}
        {--cap=6 : Max images per product}
        {--dry-run : Show the matches without writing anything}';

    protected $description = 'Replace product images from a local folder using a named slug→filename map.';

    public function handle(ProductImageImporter $importer): int
    {
        $maps = require database_path('data/image-maps.php');
        $group = $this->argument('group');

        if (! isset($maps[$group])) {
            $this->error("Unknown map group '{$group}'. Available: " . implode(', ', array_keys($maps)));

            return self::FAILURE;
        }

        $dir = $this->option('dir');
        if (! $dir || ! is_dir($dir)) {
            $this->error('Provide a valid --dir pointing at the image folder.');

            return self::FAILURE;
        }

        $cap = max(1, (int) $this->option('cap'));
        $dry = (bool) $this->option('dry-run');
        $files = $this->scan($dir);

        $this->info('Scanned ' . count($files) . ' image(s)' . ($dry ? '  (dry run — nothing will be written)' : ''));

        $totalStored = 0;
        foreach ($maps[$group] as $slug => [$include, $exclude]) {
            $product = Product::where('slug', $slug)->first();
            if (! $product) {
                $this->warn("  · no product with slug {$slug} — skipped");

                continue;
            }

            $matched = $this->match($files, $include, $exclude, $cap);
            $this->line("  · {$product->name_ar}: " . count($matched) . ' image(s)');
            foreach ($matched as $i => $path) {
                $this->line('       ' . ($i === 0 ? '★ ' : '  ') . basename($path));
            }

            if (! $dry) {
                $totalStored += $importer->replaceForProduct($product, $matched);
            }
        }

        $this->newLine();
        $this->info($dry ? 'Dry run complete.' : "Done — stored {$totalStored} image(s).");

        return self::SUCCESS;
    }

    /**
     * Image files under $dir (recursive).
     *
     * @return list<string>
     */
    private function scan(string $dir): array
    {
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Files whose name contains every $include and no $exclude, ordered with the
     * un-numbered base shot first (→ primary), then capped.
     *
     * @param  list<string>  $files
     * @param  list<string>  $include
     * @param  list<string>  $exclude
     * @return list<string>
     */
    private function match(array $files, array $include, array $exclude, int $cap): array
    {
        $matched = array_values(array_filter($files, function (string $path) use ($include, $exclude) {
            $name = pathinfo($path, PATHINFO_FILENAME);
            foreach ($include as $needle) {
                if (mb_strpos($name, $needle) === false) {
                    return false;
                }
            }
            foreach ($exclude as $needle) {
                if (mb_strpos($name, $needle) !== false) {
                    return false;
                }
            }

            return true;
        }));

        usort($matched, function (string $a, string $b) {
            $na = pathinfo($a, PATHINFO_FILENAME);
            $nb = pathinfo($b, PATHINFO_FILENAME);
            $numbered = fn (string $n) => preg_match('/\(\d+\)/', $n) ? 1 : 0;

            return [$numbered($na), $na] <=> [$numbered($nb), $nb];
        });

        return array_slice($matched, 0, $cap);
    }
}
