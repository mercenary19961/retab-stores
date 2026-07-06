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

    public function index()
    {
        $logs = ActivityLog::query()
            ->with(['user:id,name', 'revertedByUser:id,name'])
            ->latest('id')
            ->paginate(20)
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

        return Inertia::render('admin/change-log/index', ['logs' => $logs]);
    }

    public function revert(ActivityLog $activityLog)
    {
        $result = $this->changeLog->revert($activityLog);

        if ($result->ok) {
            return back()->with('success', __('messages.admin.change_reverted'));
        }

        return back()->with('error', $result->reason === RevertResult::REASON_CONFLICT
            ? __('messages.admin.change_revert_conflict', ['fields' => implode(', ', $result->conflicts)])
            : __('messages.admin.change_revert_blocked'));
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
