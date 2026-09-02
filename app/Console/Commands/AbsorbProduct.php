<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Redirect;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fold one duplicate product into another: `catalog:absorb <from> <into>`.
 *
 * The Zid catalogue lists the same item more than once — the same variety, grade
 * and pack size under two SKUs at different prices, or a live listing beside an
 * abandoned draft. `catalog:merge-variants` handled the mechanical carton/single
 * split; this handles the ones that need a HUMAN to say which listing is current,
 * because both are real and only the client knows the answer.
 *
 * 🔑 The winner is stated, never inferred. Price, stock and options are the whole
 * question, so nothing about them is guessed: the winner keeps every one of its
 * own values untouched and the loser's are discarded with the row.
 *
 * What actually moves:
 *   - text fields the winner is MISSING (name_en, descriptions, smacc_sku,
 *     barcode) — only-empty fills, the importer's convention, so a value the
 *     winner already holds is never overwritten by the row being retired;
 *   - images, only with --with-images, and only appended after the winner's own
 *     (its primary stays primary). Re-pointing product_id is enough: the stored
 *     path is a key, not a location, so no file moves and no variant regeneration;
 *   - a 301 from the loser's slug, because a live listing may be indexed and a
 *     silent 404 loses the ranking.
 *
 * ⚠️ It REPORTS rather than moves anything it cannot merge safely — reviews,
 * wishlists and order history stay with the retired row. Soft delete keeps those
 * rows valid and the product recoverable; nothing is destroyed.
 */
class AbsorbProduct extends Command
{
    protected $signature = 'catalog:absorb
        {from : SKU of the duplicate to retire}
        {into : SKU of the product to keep}
        {--with-images : Also move the retired product\'s images onto the keeper}
        {--apply : Write the changes (default is a dry-run preview)}';

    protected $description = 'Fold a duplicate product into the one being kept, with a 301 and a soft delete';

    /** Filled on the keeper only where it is currently empty. */
    private const INHERITABLE = [
        'name_en', 'description_ar', 'description_en',
        'short_description_ar', 'short_description_en', 'smacc_sku', 'barcode',
    ];

    public function handle(): int
    {
        $loser = Product::where('sku', $this->argument('from'))->first();
        $winner = Product::where('sku', $this->argument('into'))->first();

        foreach ([[$loser, 'from'], [$winner, 'into']] as [$p, $arg]) {
            if (! $p) {
                $this->error("No product with SKU {$this->argument($arg)}.");

                return self::FAILURE;
            }
        }
        if ($loser->is($winner)) {
            $this->error('A product cannot absorb itself.');

            return self::FAILURE;
        }

        $inherits = $this->inheritable($loser, $winner);
        $images = $loser->images()->count();

        $this->line('');
        $this->info("Retiring {$loser->sku} into {$winner->sku}");
        $this->table(
            ['', 'Retiring — '.$loser->sku, 'Keeping — '.$winner->sku],
            [
                ['name', mb_substr((string) $loser->name_ar, 0, 34), mb_substr((string) $winner->name_ar, 0, 34)],
                ['price', $loser->price, $winner->price],
                ['stock', $loser->stock, $winner->stock],
                ['images', $images, $winner->images()->count()],
                ['options', $loser->options()->count(), $winner->options()->count()],
                ['live', $loser->is_active ? 'yes' : 'no', $winner->is_active ? 'yes' : 'no'],
            ],
        );

        $this->line($inherits === []
            ? '  Nothing to inherit — the keeper already has every field filled.'
            : '  Fields the keeper is missing and will inherit: '.implode(', ', array_keys($inherits)));

        if ($images > 0) {
            $this->line($this->option('with-images')
                ? "  Images: {$images} will move across and be appended after the keeper's own."
                : "  Images: {$images} will be RETIRED with the row. Pass --with-images to keep them.");
        }

        $this->line("  Redirect: /products/{$loser->slug} → the keeper (301).");

        // Things that stay behind, so nobody discovers them later.
        foreach (['reviews' => $loser->reviews()->count(), 'wishlists' => $loser->wishlists()->count(), 'order lines' => $loser->orderItems()->count()] as $what => $n) {
            if ($n > 0) {
                $this->warn("  {$n} {$what} stay with the retired row (not moved).");
            }
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('Dry run — re-run with --apply to write these changes.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($loser, $winner, $inherits) {
            // 🔴 `smacc_sku` is UNIQUE, and a soft-deleted row still occupies the
            // index — so it has to be released from the retiring product BEFORE
            // the keeper can take it, or the save dies on a constraint violation.
            // It is worth transferring rather than dropping: it is the key the
            // daily SMACC stock import matches on, so the keeper is unsyncable
            // without it.
            if (array_key_exists('smacc_sku', $inherits)) {
                $loser->forceFill(['smacc_sku' => null])->save();
            }

            if ($inherits !== []) {
                $winner->fill($inherits)->save();
            }

            if ($this->option('with-images')) {
                // Append after the keeper's own so its primary stays primary.
                $offset = (int) $winner->images()->max('sort_order');
                foreach ($loser->images()->orderBy('sort_order')->get() as $i => $image) {
                    $image->update([
                        'product_id' => $winner->id,
                        'is_primary' => false,
                        'sort_order' => $offset + $i + 1,
                    ]);
                }
            }

            Redirect::firstOrCreate(
                ['from_slug' => $loser->slug],
                ['product_id' => $winner->id, 'status' => 301],
            );

            $loser->delete();
        });

        $this->newLine();
        $this->info("Done. {$loser->sku} retired; {$winner->sku} kept.");

        return self::SUCCESS;
    }

    /**
     * Values the keeper is missing that the retiring row can supply.
     *
     * @return array<string, string>
     */
    private function inheritable(Product $loser, Product $winner): array
    {
        $fill = [];
        foreach (self::INHERITABLE as $field) {
            if (trim((string) $winner->{$field}) === '' && trim((string) $loser->{$field}) !== '') {
                $fill[$field] = $loser->{$field};
            }
        }

        return $fill;
    }
}
