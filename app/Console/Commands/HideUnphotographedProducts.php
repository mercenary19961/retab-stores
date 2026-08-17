<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hide every product that is still showing imported Zid imagery, keeping the
 * storefront to products with real client photography.
 *
 * The rule is derived, not hand-listed: a product stays visible when its SKU or
 * slug appears in ANY group of database/data/image-maps.php, because those groups
 * are exactly the record of which products received a client photo delivery (the
 * July `rusks` set and the August `photoshoot-2026-08` studio set). Everything
 * else is on a Zid photo and gets hidden.
 *
 * 🔑 Hiding, never deleting. `is_active = false` also makes a product unbuyable
 * (the cart 404s it), so this is a complete removal from the shopfront while the
 * row, its images, its SKU and its slug all survive untouched.
 *
 * ⚠️ Reversibility is the whole point, so the SKUs this command hides are recorded
 * in `settings` under RECORD_KEY and `--restore` reactivates EXACTLY those. That
 * record is essential rather than convenient: most of the catalogue's drafts were
 * already hidden before this ran (incomplete Zid imports, the four photoshoot
 * products awaiting prices), and a restore that simply activated everything
 * un-photographed would silently publish those too.
 */
class HideUnphotographedProducts extends Command
{
    protected $signature = 'catalog:hide-unphotographed
        {--restore : Reactivate only the products a previous run hid}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Hide products still showing Zid imagery, keeping those with client photography.';

    /** Where the hidden SKUs are recorded so --restore can be exact. */
    private const RECORD_KEY = 'products_hidden_pending_photos';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        return $this->option('restore') ? $this->restore($dry) : $this->hide($dry);
    }

    private function hide(bool $dry): int
    {
        $keep = $this->photographedIds();
        $this->info('Products with client photography: '.$keep->count());

        $targets = Product::whereNotIn('id', $keep)
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get();

        if ($targets->isEmpty()) {
            $this->info('Nothing to hide — every visible product has client photography.');

            return self::SUCCESS;
        }

        foreach ($targets as $p) {
            $this->line(sprintf('  · %-8s %s', $p->sku, $p->name_ar));
        }

        $this->newLine();
        $this->warn("Would hide {$targets->count()} product(s); ".$keep->count().' stay visible.');

        if ($dry) {
            $this->info('Dry run — nothing written.');

            return self::SUCCESS;
        }

        // Union with any earlier run's record, so hiding in batches still leaves a
        // single complete list for --restore. Without the union a second run would
        // overwrite the record and orphan the first batch as un-restorable.
        $recorded = collect($this->recorded())
            ->merge($targets->pluck('sku'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($targets, $recorded) {
            Product::whereIn('id', $targets->pluck('id'))->update(['is_active' => false]);
            Setting::set(self::RECORD_KEY, json_encode($recorded, JSON_UNESCAPED_UNICODE));
        });

        $this->info("Hidden {$targets->count()} product(s). Recorded ".count($recorded).' for --restore.');

        return self::SUCCESS;
    }

    private function restore(bool $dry): int
    {
        $skus = $this->recorded();

        if ($skus === []) {
            $this->warn('No record of a previous run — nothing to restore.');

            return self::SUCCESS;
        }

        $targets = Product::whereIn('sku', $skus)->where('is_active', false)->get();

        $this->info('Recorded SKUs: '.count($skus).' · currently hidden: '.$targets->count());
        foreach ($targets as $p) {
            $this->line(sprintf('  · %-8s %s', $p->sku, $p->name_ar));
        }

        if ($dry) {
            $this->info('Dry run — nothing written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($targets) {
            Product::whereIn('id', $targets->pluck('id'))->update(['is_active' => true]);
            Setting::set(self::RECORD_KEY, json_encode([], JSON_UNESCAPED_UNICODE));
        });

        $this->info("Restored {$targets->count()} product(s).");

        return self::SUCCESS;
    }

    /**
     * Ids of every product named by an image map, resolving each key as a slug or
     * a SKU (the maps allow both — see database/data/image-maps.php).
     *
     * @return Collection<int, int>
     */
    private function photographedIds(): Collection
    {
        $keys = collect(require database_path('data/image-maps.php'))
            ->flatMap(fn (array $group) => array_keys($group))
            ->unique();

        return Product::whereIn('slug', $keys)
            ->orWhereIn('sku', $keys)
            ->pluck('id');
    }

    /** @return list<string> */
    private function recorded(): array
    {
        $raw = Setting::get(self::RECORD_KEY);

        return is_string($raw) ? (array) json_decode($raw, true) : [];
    }
}
