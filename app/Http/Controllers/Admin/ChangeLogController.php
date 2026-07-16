<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ChangeLog\ChangeLogService;
use App\Services\ChangeLog\RevertResult;
use App\Services\Smacc\SmaccImportService;
use Inertia\Inertia;

/**
 * Staff-facing audit history of tracked admin edits, with per-entry Revert.
 * Logging happens in the respective controllers via ChangeLogService; SMACC
 * stock imports appear here audit-only (their undo lives on the Inventory page).
 */
class ChangeLogController extends Controller
{
    public function __construct(private ChangeLogService $changeLog) {}

    public function index(\Illuminate\Http\Request $request)
    {
        // Jump to the page containing a specific entry (the conflict "take me to
        // it" link) — logs are id-desc, so the entry's position is the count of
        // entries newer-or-equal to it.
        $highlight = (int) $request->query('highlight');
        $page = null;
        if ($highlight > 0) {
            $position = ActivityLog::where('id', '>=', $highlight)->count();
            $page = max(1, (int) ceil($position / 20));
        }

        $logs = ActivityLog::query()
            ->with(['user:id,name', 'revertedByUser:id,name'])
            ->latest('id')
            ->paginate(20, ['*'], 'page', $page)
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'section' => $log->action === SmaccImportService::ACTION
                    ? 'Inventory'
                    : $this->changeLog->sectionLabel($log),
                'action' => $log->action,
                'label' => $log->label ?? $this->fallbackLabel($log),
                'changes' => $this->changeLog->diff($log),
                'user' => $log->user?->name,
                'created_at' => $log->created_at?->toDateTimeString(),
                'revertable' => $this->changeLog->revertable($log),
                'reverted_at' => $log->reverted_at?->toDateTimeString(),
                'reverted_by' => $log->revertedByUser?->name,
                'reverts_log_id' => $log->reverts_log_id,
            ]);

        return Inertia::render('admin/change-log/index', [
            'logs' => $logs,
            'highlight' => $highlight ?: null,
        ]);
    }

    public function revert(ActivityLog $activityLog)
    {
        $result = $this->changeLog->revert($activityLog);

        if ($result->ok) {
            // The quick "undo last save" button for that section has served its
            // purpose — clear it so it doesn't linger pointing at a reverted change.
            if ($section = $this->changeLog->sectionKey($activityLog)) {
                $this->changeLog->clearUndo($section);
            }

            return back()->with('success', __('messages.admin.change_reverted'));
        }

        if ($result->reason === RevertResult::REASON_CONFLICT) {
            // Structured so the UI can name the blocked fields and link to the
            // later change that has to be undone first.
            return back()->with('revertConflict', [
                'fields' => $result->conflicts,
                'blockerId' => $result->blockerId,
                'blockerLabel' => $result->blockerLabel,
            ]);
        }

        return back()->with('error', __('messages.admin.change_revert_blocked'));
    }

    /** Dismiss a section's "undo last save" pointer without reverting anything. */
    public function dismissUndo(string $section)
    {
        $this->changeLog->clearUndo($section);

        return back();
    }

    /** Display label for bespoke entries that predate / bypass the generic logger. */
    private function fallbackLabel(ActivityLog $log): ?string
    {
        if ($log->action === SmaccImportService::ACTION) {
            $updated = $log->changes['summary']['updated'] ?? 0;

            return "Stock import ({$updated} products)";
        }

        return null;
    }
}
