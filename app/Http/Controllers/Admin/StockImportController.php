<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Smacc\SmaccImportService;
use App\Support\TableExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * SMACC → website stock sync (CSV upload). Two steps: preview the diff, then
 * apply. The uploaded file is parked on disk between the two requests and keyed
 * by an opaque token; apply re-reads it so we never trust client-submitted diffs.
 */
class StockImportController extends Controller
{
    private const DIR = 'smacc-imports';

    public function __construct(
        protected SmaccImportService $service,
    ) {}

    /** Full field set for the current-stock export, in column order. */
    private const EXPORT_COLUMNS = [
        'sku', 'smacc_sku', 'barcode', 'name_ar', 'category', 'stock',
        'low_stock_threshold', 'is_low_stock', 'is_active', 'updated_at',
    ];

    public function index()
    {
        return Inertia::render('admin/stock-import/index', [
            'lastSynced' => $this->lastSyncedPayload(),
            'history' => $this->recentImports(),
        ]);
    }

    /**
     * Snapshot of current website stock (CSV / XLSX / JSON). Feeds the daily
     * SMACC reconciliation routine; `?low=1` limits it to low-stock items.
     */
    public function export(Request $request)
    {
        $lowOnly = (bool) $request->query('low');

        $rows = Product::query()
            ->with('category:id,name_ar')
            ->when($lowOnly, fn ($q) => $q->whereRaw('stock <= COALESCE(low_stock_threshold, ?)', [5]))
            ->orderBy('name_ar')
            ->get()
            ->map(fn (Product $p) => [
                'sku' => $p->sku,
                'smacc_sku' => $p->smacc_sku,
                'barcode' => $p->barcode,
                'name_ar' => $p->name_ar,
                'category' => $p->category?->name_ar,
                'stock' => $p->stock,
                'low_stock_threshold' => $p->low_stock_threshold,
                'is_low_stock' => (int) $p->isLowStock(),
                'is_active' => (int) $p->is_active,
                'updated_at' => $p->updated_at?->toDateTimeString(),
            ]);

        return TableExport::download((string) $request->query('format'), 'inventory-stock', self::EXPORT_COLUMNS, $rows);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $token = Str::uuid() . '.csv';
        $request->file('file')->storeAs(self::DIR, $token, 'local');

        try {
            $rows = $this->service->parse(Storage::disk('local')->path(self::DIR . '/' . $token));
        } catch (\RuntimeException $e) {
            Storage::disk('local')->delete(self::DIR . '/' . $token);

            return back()->with('error', $e->getMessage());
        }

        return Inertia::render('admin/stock-import/preview', [
            'token' => $token,
            'diff' => $this->service->diff($rows),
        ]);
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $relative = self::DIR . '/' . basename($data['token']); // basename guards path traversal
        if (! Storage::disk('local')->exists($relative)) {
            return redirect()->route('admin.stock-import.index')
                ->with('error', __('messages.admin.import_expired'));
        }

        try {
            $rows = $this->service->parse(Storage::disk('local')->path($relative));
            $log = $this->service->apply($rows, Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } finally {
            Storage::disk('local')->delete($relative);
        }

        $updated = $log->changes['summary']['updated'] ?? 0;

        return redirect()->route('admin.stock-import.index')
            ->with('success', __('messages.admin.import_applied', ['count' => $updated]));
    }

    public function undo(ActivityLog $activityLog)
    {
        try {
            $this->service->undo($activityLog, Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('messages.admin.import_undone'));
    }

    /**
     * @return array{at:string|null, hours:int|null, stale:bool}
     */
    private function lastSyncedPayload(): array
    {
        $raw = Setting::get(SmaccImportService::LAST_SYNCED_KEY);
        if (! $raw) {
            return ['at' => null, 'hours' => null, 'stale' => true];
        }

        $at = Carbon::parse($raw);
        $hours = (int) $at->diffInHours(now());

        return ['at' => $at->toDateTimeString(), 'hours' => $hours, 'stale' => $hours >= 24];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentImports(): array
    {
        return ActivityLog::where('action', SmaccImportService::ACTION)
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'updated' => $log->changes['summary']['updated'] ?? 0,
                'unmatched' => $log->changes['summary']['unmatched'] ?? 0,
                'user' => $log->user?->name,
                'created_at' => $log->created_at?->toDateTimeString(),
                'reverted_at' => $log->reverted_at?->toDateTimeString(),
            ])
            ->all();
    }
}
