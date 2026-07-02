<?php

namespace App\Services\Smacc;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Re-baselines website stock from a SMACC export. SMACC is the ledger of record;
 * the website is a daily-synced mirror (no live API — see CLAUDE.md → POS). v1
 * ingests CSV (admin "Save As CSV" from the SMACC .xlsx export).
 *
 * Flow: parse → diff (match by smacc_sku, fallback barcode) → apply (transactional,
 * idempotent) → one ActivityLog row with before/after for undo. Re-applying the
 * same file is a no-op because matched stock already equals the file's value, so
 * the daily routine can't "refill" already-sold units.
 */
class SmaccImportService
{
    public const LAST_SYNCED_KEY = 'stock_last_synced_at';

    public const ACTION = 'stock_import';

    /** Header aliases (lower-cased) → canonical field. */
    private const KEY_COLUMNS = ['smacc_sku', 'sku', 'code', 'item_code', 'itemcode'];

    private const BARCODE_COLUMNS = ['barcode', 'bar_code'];

    private const STOCK_COLUMNS = ['stock', 'quantity', 'qty', 'balance', 'available', 'on_hand'];

    /**
     * Parse a CSV file into normalized rows.
     *
     * @return list<array{line:int, smacc_sku:string, barcode:string, stock:int|null, error:string|null}>
     */
    public function parse(string $path): array
    {
        $handle = @fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('Could not open the uploaded file.');
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === [null]) {
                throw new RuntimeException('The file is empty.');
            }

            // Strip a UTF-8 BOM from the first header cell.
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $map = $this->mapColumns($header);

            if (! isset($map['stock']) || (! isset($map['smacc_sku']) && ! isset($map['barcode']))) {
                throw new RuntimeException('The file needs a stock column and a SMACC SKU (or barcode) column.');
            }

            $rows = [];
            $line = 1;
            while (($data = fgetcsv($handle)) !== false) {
                $line++;
                if ($this->isBlank($data)) {
                    continue;
                }

                $smacc = isset($map['smacc_sku']) ? trim((string) ($data[$map['smacc_sku']] ?? '')) : '';
                $barcode = isset($map['barcode']) ? trim((string) ($data[$map['barcode']] ?? '')) : '';
                $stockRaw = trim((string) ($data[$map['stock']] ?? ''));

                if ($smacc === '' && $barcode === '') {
                    continue; // no key — skip silently
                }

                if (! is_numeric($stockRaw) || (int) $stockRaw < 0) {
                    $rows[] = ['line' => $line, 'smacc_sku' => $smacc, 'barcode' => $barcode, 'stock' => null, 'error' => 'invalid stock value'];

                    continue;
                }

                $rows[] = ['line' => $line, 'smacc_sku' => $smacc, 'barcode' => $barcode, 'stock' => (int) $stockRaw, 'error' => null];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Compare parsed rows to current stock. Buckets: matched (stock changes),
     * unchanged, unmatched (no product), invalid (bad row).
     *
     * @param  list<array{line:int, smacc_sku:string, barcode:string, stock:int|null, error:string|null}>  $rows
     * @return array{matched:list<array>, unchanged:list<array>, unmatched:list<array>, invalid:list<array>}
     */
    public function diff(array $rows): array
    {
        $smaccKeys = array_values(array_filter(array_column($rows, 'smacc_sku')));
        $barcodes = array_values(array_filter(array_column($rows, 'barcode')));

        $bySmacc = $smaccKeys
            ? Product::whereIn('smacc_sku', $smaccKeys)->get()->keyBy('smacc_sku')
            : collect();
        $byBarcode = $barcodes
            ? Product::whereIn('barcode', $barcodes)->get()->keyBy('barcode')
            : collect();

        $matched = [];
        $unchanged = [];
        $unmatched = [];
        $invalid = [];

        foreach ($rows as $row) {
            if ($row['error'] !== null) {
                $invalid[] = $row;

                continue;
            }

            $product = ($row['smacc_sku'] !== '' ? $bySmacc->get($row['smacc_sku']) : null)
                ?? ($row['barcode'] !== '' ? $byBarcode->get($row['barcode']) : null);

            if (! $product) {
                $unmatched[] = $row;

                continue;
            }

            $old = (int) $product->stock;
            $new = (int) $row['stock'];
            $entry = ['product_id' => $product->id, 'name' => $product->name_ar, 'sku' => $product->sku];

            if ($old === $new) {
                $unchanged[] = $entry + ['stock' => $old];

                continue;
            }

            $matched[] = $entry + ['old' => $old, 'new' => $new];
        }

        return compact('matched', 'unchanged', 'unmatched', 'invalid');
    }

    /**
     * Apply the diff: update stock for every changed match, write one undoable
     * ActivityLog, and stamp the last-synced setting. Idempotent.
     *
     * @param  list<array{line:int, smacc_sku:string, barcode:string, stock:int|null, error:string|null}>  $rows
     */
    public function apply(array $rows, ?int $userId = null): ActivityLog
    {
        $diff = $this->diff($rows);

        return DB::transaction(function () use ($diff, $userId) {
            $changes = [];
            foreach ($diff['matched'] as $m) {
                Product::whereKey($m['product_id'])->update(['stock' => $m['new']]);
                $changes[] = ['product_id' => $m['product_id'], 'sku' => $m['sku'], 'old' => $m['old'], 'new' => $m['new']];
            }

            $log = ActivityLog::create([
                'user_id' => $userId,
                'action' => self::ACTION,
                'changes' => [
                    'products' => $changes,
                    'summary' => [
                        'updated' => count($changes),
                        'unmatched' => count($diff['unmatched']),
                        'unchanged' => count($diff['unchanged']),
                        'invalid' => count($diff['invalid']),
                    ],
                ],
            ]);

            Setting::set(self::LAST_SYNCED_KEY, now()->toIso8601String());

            return $log;
        });
    }

    /**
     * Revert a stock import — restore each product to its pre-import value.
     */
    public function undo(ActivityLog $log, ?int $userId = null): void
    {
        if ($log->action !== self::ACTION || $log->reverted_at !== null) {
            throw new RuntimeException('This import can no longer be undone.');
        }

        DB::transaction(function () use ($log, $userId) {
            foreach (($log->changes['products'] ?? []) as $c) {
                Product::whereKey($c['product_id'])->update(['stock' => $c['old']]);
            }

            $log->update(['reverted_at' => now(), 'reverted_by' => $userId]);
        });
    }

    /**
     * Map header cells to canonical field indexes (case-insensitive).
     *
     * @param  array<int, string|null>  $header
     * @return array{smacc_sku?:int, barcode?:int, stock?:int}
     */
    private function mapColumns(array $header): array
    {
        $map = [];
        foreach ($header as $i => $cell) {
            $name = strtolower(trim((string) $cell));
            if (! isset($map['smacc_sku']) && in_array($name, self::KEY_COLUMNS, true)) {
                $map['smacc_sku'] = $i;
            } elseif (! isset($map['barcode']) && in_array($name, self::BARCODE_COLUMNS, true)) {
                $map['barcode'] = $i;
            } elseif (! isset($map['stock']) && in_array($name, self::STOCK_COLUMNS, true)) {
                $map['stock'] = $i;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isBlank(array $row): bool
    {
        return $row === [null] || implode('', array_map(fn ($c) => trim((string) $c), $row)) === '';
    }
}
