<?php

namespace App\Services\Discount;

use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bulk + CSV product discounts. Writes a scheduled sale_price (+ window) onto
 * products; Product::isOnSale() honours the window, so the storefront, cart and
 * checkout all become schedule-aware from one place.
 *
 * Every apply/clear writes one undoable ActivityLog (before/after per product),
 * mirroring the SMACC stock import. Audit-only in the change log; undo lives on
 * the discounts page.
 */
class DiscountService
{
    public const ACTION = 'discount_apply';

    /** CSV header aliases (lower-cased) → canonical field. */
    private const KEY_COLUMNS = ['sku', 'smacc_sku', 'code', 'item_code'];

    private const PERCENT_COLUMNS = ['discount_percent', 'discount', 'percent', 'percentage', 'off'];

    /**
     * Apply one percentage off to every active product in scope (all, or a
     * category), over an optional schedule window.
     */
    public function bulkApply(string $discountMode, float $value, ?float $maxCap, ?int $categoryId, ?Carbon $startsAt, ?Carbon $endsAt, ?int $userId = null): ActivityLog
    {
        $products = Product::where('is_active', true)
            ->where('price', '>', 0)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->get(['id', 'sku', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at']);

        return $this->applyToProducts(
            $products,
            fn (Product $p) => $this->saleFor((float) $p->price, $discountMode, $value, $maxCap),
            $startsAt,
            $endsAt,
            $userId,
            ['mode' => 'bulk', 'discount_mode' => $discountMode, 'value' => $value, 'category_id' => $categoryId],
        );
    }

    /** New sale price for a percentage or fixed-amount discount (cap applies to %). */
    private function saleFor(float $price, string $mode, float $value, ?float $maxCap): float
    {
        $discount = $mode === 'fixed' ? $value : $price * $value / 100;
        if ($mode === 'percentage' && $maxCap !== null) {
            $discount = min($discount, $maxCap);
        }

        return round(max(0, $price - $discount), 2);
    }

    /**
     * Apply per-product percentages parsed from a CSV, over an optional window.
     *
     * @param  list<array{line:int, sku:string, percent:float|null, error:string|null}>  $rows
     */
    public function applyImport(array $rows, ?Carbon $startsAt, ?Carbon $endsAt, ?int $userId = null): ActivityLog
    {
        $diff = $this->diff($rows);
        $newByProduct = [];
        foreach ($diff['matched'] as $m) {
            $newByProduct[$m['product_id']] = $m['new'];
        }

        $products = Product::whereIn('id', array_keys($newByProduct))
            ->get(['id', 'sku', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at']);

        return $this->applyToProducts(
            $products,
            fn (Product $p) => $newByProduct[$p->id] ?? null,
            $startsAt,
            $endsAt,
            $userId,
            ['mode' => 'import', 'unmatched' => count($diff['unmatched']), 'invalid' => count($diff['invalid'])],
        );
    }

    /**
     * Clear the sale (price + window) for the given products.
     *
     * @param  list<int>  $productIds
     */
    public function clear(array $productIds, ?int $userId = null): ActivityLog
    {
        $products = Product::whereIn('id', $productIds)
            ->whereNotNull('sale_price')
            ->get(['id', 'sku', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at']);

        return DB::transaction(function () use ($products, $userId) {
            $changes = [];
            foreach ($products as $p) {
                $changes[] = $this->snapshot($p) + ['new_sale' => null];
                Product::whereKey($p->id)->update(['sale_price' => null, 'sale_starts_at' => null, 'sale_ends_at' => null]);
            }

            return ActivityLog::create([
                'user_id' => $userId,
                'action' => self::ACTION,
                'changes' => ['products' => $changes, 'summary' => ['mode' => 'clear', 'applied' => count($changes)]],
            ]);
        });
    }

    /** Restore each product's pre-change sale price + window. */
    public function undo(ActivityLog $log, ?int $userId = null): void
    {
        if ($log->action !== self::ACTION || $log->reverted_at !== null) {
            throw new RuntimeException(__('messages.admin.discount_cannot_undo'));
        }

        DB::transaction(function () use ($log, $userId) {
            foreach (($log->changes['products'] ?? []) as $c) {
                Product::whereKey($c['product_id'])->update([
                    'sale_price' => $c['old_sale'],
                    'sale_starts_at' => $c['old_starts'],
                    'sale_ends_at' => $c['old_ends'],
                ]);
            }

            $log->update(['reverted_at' => now(), 'reverted_by' => $userId]);
        });
    }

    /**
     * Parse a CSV of `sku, discount_percent` rows.
     *
     * @return list<array{line:int, sku:string, percent:float|null, error:string|null}>
     */
    public function parse(string $path): array
    {
        $handle = @fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException(__('messages.admin.import_open_failed'));
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === [null]) {
                throw new RuntimeException(__('messages.admin.import_empty'));
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $map = $this->mapColumns($header);

            if (! isset($map['sku']) || ! isset($map['percent'])) {
                throw new RuntimeException(__('messages.admin.discount_import_columns'));
            }

            $rows = [];
            $line = 1;
            while (($data = fgetcsv($handle)) !== false) {
                $line++;
                if ($this->isBlank($data)) {
                    continue;
                }

                $sku = trim((string) ($data[$map['sku']] ?? ''));
                $pctRaw = trim((string) ($data[$map['percent']] ?? ''));
                if ($sku === '') {
                    continue;
                }

                if (! is_numeric($pctRaw) || (float) $pctRaw <= 0 || (float) $pctRaw >= 100) {
                    $rows[] = ['line' => $line, 'sku' => $sku, 'percent' => null, 'error' => 'invalid discount %'];

                    continue;
                }

                $rows[] = ['line' => $line, 'sku' => $sku, 'percent' => (float) $pctRaw, 'error' => null];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Match parsed rows to products (by sku, fallback smacc_sku) and compute each
     * new sale price. Buckets: matched / unmatched / invalid.
     *
     * @param  list<array{line:int, sku:string, percent:float|null, error:string|null}>  $rows
     * @return array{matched:list<array>, unmatched:list<array>, invalid:list<array>}
     */
    public function diff(array $rows): array
    {
        $skus = array_values(array_filter(array_column($rows, 'sku')));
        $bySku = $skus ? Product::whereIn('sku', $skus)->get()->keyBy('sku') : collect();
        $bySmacc = $skus ? Product::whereIn('smacc_sku', $skus)->get()->keyBy('smacc_sku') : collect();

        $matched = [];
        $unmatched = [];
        $invalid = [];

        foreach ($rows as $row) {
            if ($row['error'] !== null) {
                $invalid[] = $row;

                continue;
            }

            $product = $bySku->get($row['sku']) ?? $bySmacc->get($row['sku']);
            if (! $product) {
                $unmatched[] = $row;

                continue;
            }

            $new = $this->priceAfter((float) $product->price, (float) $row['percent']);
            if ($new >= (float) $product->price) {
                $invalid[] = $row + ['error' => 'no discount'];

                continue;
            }

            $matched[] = [
                'product_id' => $product->id,
                'name' => $product->name_ar,
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'percent' => (float) $row['percent'],
                'new' => $new,
            ];
        }

        return compact('matched', 'unmatched', 'invalid');
    }

    /**
     * Write the computed sale price + window to each product and log it undoably.
     * `$newSaleFor` returns the new sale price for a product, or null to skip.
     *
     * @param  Collection<int, Product>  $products
     * @param  callable(Product): ?float  $newSaleFor
     * @param  array<string, mixed>  $meta
     */
    private function applyToProducts(Collection $products, callable $newSaleFor, ?Carbon $startsAt, ?Carbon $endsAt, ?int $userId, array $meta): ActivityLog
    {
        return DB::transaction(function () use ($products, $newSaleFor, $startsAt, $endsAt, $userId, $meta) {
            $changes = [];
            foreach ($products as $p) {
                $newSale = $newSaleFor($p);
                if ($newSale === null || $newSale >= (float) $p->price) {
                    continue; // 0% / rounding produced no real discount
                }

                $changes[] = $this->snapshot($p) + ['new_sale' => $newSale];
                Product::whereKey($p->id)->update([
                    'sale_price' => $newSale,
                    'sale_starts_at' => $startsAt,
                    'sale_ends_at' => $endsAt,
                ]);
            }

            return ActivityLog::create([
                'user_id' => $userId,
                'action' => self::ACTION,
                'changes' => [
                    'products' => $changes,
                    'window' => ['starts_at' => $startsAt?->toDateTimeString(), 'ends_at' => $endsAt?->toDateTimeString()],
                    'summary' => $meta + ['applied' => count($changes)],
                ],
            ]);
        });
    }

    /** Pre-change snapshot of a product's sale fields (for undo). */
    private function snapshot(Product $p): array
    {
        return [
            'product_id' => $p->id,
            'sku' => $p->sku,
            'old_sale' => $p->sale_price !== null ? (float) $p->sale_price : null,
            'old_starts' => $p->sale_starts_at?->toDateTimeString(),
            'old_ends' => $p->sale_ends_at?->toDateTimeString(),
        ];
    }

    /** sale_price after a percentage off, rounded to 2dp, floored at 0. */
    private function priceAfter(float $price, float $percent): float
    {
        return round(max(0, $price * (1 - $percent / 100)), 2);
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array{sku?:int, percent?:int}
     */
    private function mapColumns(array $header): array
    {
        $map = [];
        foreach ($header as $i => $cell) {
            $name = strtolower(trim((string) $cell));
            if (! isset($map['sku']) && in_array($name, self::KEY_COLUMNS, true)) {
                $map['sku'] = $i;
            } elseif (! isset($map['percent']) && in_array($name, self::PERCENT_COLUMNS, true)) {
                $map['percent'] = $i;
            }
        }

        return $map;
    }

    /** @param array<int, string|null> $row */
    private function isBlank(array $row): bool
    {
        return $row === [null] || implode('', array_map(fn ($c) => trim((string) $c), $row)) === '';
    }
}
