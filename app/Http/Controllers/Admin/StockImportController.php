<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\Smacc\SmaccImportService;
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

    public function index()
    {
        return Inertia::render('admin/stock-import/index', [
            'lastSynced' => $this->lastSyncedPayload(),
            'history' => $this->recentImports(),
        ]);
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
                ->with('error', 'انتهت صلاحية ملف الاستيراد. يُرجى رفعه من جديد.');
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
            ->with('success', "تم تحديث المخزون: {$updated} منتج.");
    }

    public function undo(ActivityLog $activityLog)
    {
        try {
            $this->service->undo($activityLog, Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم التراجع عن الاستيراد واستعادة المخزون السابق.');
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
