<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Redirect;
use App\Support\ArabicSlug;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time (idempotent) launch task: replace "junk" product slugs left by the Zid
 * import — ar-prefixed, English/uppercase, or leading-hyphen — with clean Arabic
 * slugs derived from name_ar, and record a 301 redirect from every changed slug so
 * the URLs Google already indexed don't 404 on cutover (see CLAUDE.md → SEO).
 *
 * Already-clean Arabic slugs are LEFT UNTOUCHED (no redirect), which is what keeps
 * the redirect set small and preserves the most ranking. A junk slug that happens
 * to regenerate to itself (e.g. a mostly-Arabic slug carrying a real "vip" token)
 * is a no-op too. Safe to re-run: cleaned slugs are no longer junk, so they're
 * skipped, and redirects are created with firstOrCreate.
 */
class CleanProductSlugs extends Command
{
    protected $signature = 'catalog:clean-slugs
        {--apply : Write the changes (default is a dry-run preview)}';

    protected $description = 'Normalise junk product slugs to clean Arabic and record 301 redirects from the old slugs';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $products = Product::orderBy('id')->get(['id', 'name_ar', 'slug']);

        // Slugs that will still exist after cleanup: every clean (kept) slug, plus
        // any slug already used as a redirect source — new slugs must dodge both so
        // nothing collides or shadows an existing redirect.
        $taken = [];
        foreach ($products as $p) {
            if (! $this->isJunk($p->slug)) {
                $taken[$p->slug] = true;
            }
        }
        foreach (Redirect::pluck('from_slug') as $from) {
            $taken[$from] = true;
        }

        /** @var array<int, array{0:Product,1:string,2:string}> $changes */
        $changes = [];
        foreach ($products as $p) {
            if (! $this->isJunk($p->slug)) {
                continue;
            }

            $base = ArabicSlug::make($p->name_ar) ?: 'product-'.$p->id;
            $new = $this->dedupe($base, $taken);

            if ($new === $p->slug) {
                continue; // junk-looking but already the right slug — leave it.
            }

            $taken[$new] = true;
            $changes[] = [$p, $p->slug, $new];
        }

        if ($changes === []) {
            $this->info('All product slugs are already clean. Nothing to do.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Old slug (→ 301)', 'New slug'],
            array_map(fn ($c) => [$c[0]->id, $c[1], $c[2]], $changes),
        );
        $this->line(count($changes).' slug(s) to clean; each old slug gets a 301 redirect.');

        if (! $apply) {
            $this->warn('Dry run — re-run with --apply to write these changes.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as [$p, $old, $new]) {
                // Old (indexed) slug → this product, as a permanent redirect.
                Redirect::firstOrCreate(
                    ['from_slug' => $old],
                    ['product_id' => $p->id, 'status' => 301],
                );
                $p->slug = $new;
                $p->save();
            }
        });

        $this->info('Applied '.count($changes).' slug change(s) and recorded the redirects.');

        return self::SUCCESS;
    }

    /**
     * Junk = a slug that is not clean Arabic: it carries a Latin letter (English /
     * uppercase / the "ar-" locale prefix) or starts with a stray hyphen. Pure
     * Arabic slugs (optionally with ASCII digits) are considered clean.
     */
    private function isJunk(string $slug): bool
    {
        return str_starts_with($slug, '-')
            || preg_match('/[a-zA-Z]/', $slug) === 1;
    }

    /** Suffix -2, -3, … until the slug is unused; records the winner in $taken. */
    private function dedupe(string $value, array &$taken): string
    {
        $candidate = $value;
        $n = 1;
        while (isset($taken[$candidate])) {
            $n++;
            $candidate = $value.'-'.$n;
        }
        $taken[$candidate] = true;

        return $candidate;
    }
}
