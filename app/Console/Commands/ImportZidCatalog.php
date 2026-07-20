<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ZidCatalogImporter;
use App\Support\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportZidCatalog extends Command
{
    protected $signature = 'catalog:import-zid
        {--path= : CSV path (defaults to database/data/retab-products.csv)}
        {--no-images : Skip downloading + attaching product images}
        {--fresh : Wipe existing products, product images, and categories first}
        {--force : Skip the --fresh confirmation prompt}';

    protected $description = 'Import the Retab catalogue migrated from Zid (categories, products, images).';

    public function handle(ZidCatalogImporter $importer): int
    {
        $path = $this->option('path') ?: database_path('data/retab-products.csv');

        if (! is_file($path)) {
            $this->error("Catalogue CSV not found: {$path}");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->warn('--fresh wipes ALL products, product images, and categories (orders keep their snapshots).');
            if (! $this->option('force') && ! $this->confirm('Continue?', false)) {
                return self::FAILURE;
            }
            $this->wipeCatalogue();
            $this->line('Existing catalogue wiped.');
        }

        $withImages = ! $this->option('no-images');
        $this->info('Importing ' . ($withImages ? 'with images' : 'without images') . ' from ' . basename($path) . ' …');

        $result = $importer->import($path, $withImages, function (Product $p) {
            $this->line('  • ' . $p->name_ar . ($p->is_active ? '' : '  (hidden)'));
        });

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Products saved', $result->productsSaved],
            ['  of which hidden (drafts)', $result->drafts],
            ['Images stored', $result->imagesSaved],
            ['Images failed', $result->imagesFailed],
        ]);

        if ($result->imagesFailed > 0) {
            $this->warn("{$result->imagesFailed} image(s) failed — see the log. Re-run to retry (only missing images are re-fetched).");
        }

        return self::SUCCESS;
    }

    /** Remove the current catalogue (files + rows) before a clean re-import. */
    private function wipeCatalogue(): void
    {
        foreach (ProductImage::query()->pluck('path') as $storedPath) {
            Media::delete($storedPath);
        }

        Schema::disableForeignKeyConstraints();
        ProductImage::query()->delete();
        Product::withTrashed()->forceDelete();
        Category::query()->delete();
        Schema::enableForeignKeyConstraints();
    }
}
