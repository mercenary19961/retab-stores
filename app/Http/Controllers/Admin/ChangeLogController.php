<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ChangeLog\ChangeLogService;
use App\Services\ChangeLog\RevertResult;
use App\Services\Smacc\SmaccImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Staff-facing audit history of tracked admin edits, with per-entry Revert.
 * Logging happens in the respective controllers via ChangeLogService; SMACC
 * stock imports appear here audit-only (their undo lives on the Inventory page).
 */
class ChangeLogController extends Controller
{
    public function __construct(private ChangeLogService $changeLog) {}

    public function index(Request $request)
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
                // "Go to item": where to edit the subject, and which fields to
                // highlight there. Hidden for deletes (the subject is trashed).
                'edit_url' => $log->action === ActivityLog::ACTION_DELETED ? null : $this->changeLog->editUrl($log),
                'fields' => $this->changeLog->changedKeys($log),
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
                'chainDepth' => $result->chainDepth,
                'editUrl' => $result->editUrl,
            ]);
        }

        return back()->with('error', __('messages.admin.change_revert_blocked'));
    }

    /**
     * Revert several selected entries in one action.
     *
     * 🔴 NEWEST-FIRST is mandatory, not tidiness. A revert writes the entry's
     * `old_data` back, so for two entries A (older) and B (newer) on one record:
     *   oldest first — revert A, record returns to pre-A. Then revert B writes
     *                  pre-B, which is the state A had already produced. A's
     *                  revert is silently undone.
     *   newest first — revert B, then A. The record lands at pre-A, which is
     *                  what selecting both promised.
     * It matters more here than in a simpler log because revert() also does
     * per-field conflict detection: oldest-first would see the newer entry as a
     * conflict and refuse nearly every row. Do NOT reorder into a bare whereIn.
     *
     * Each entry goes through the SAME ChangeLogService::revert() the single-row
     * button uses, so the two can never drift, and each is transactional on its
     * own — a partial result is a legitimate outcome here, and rolling back the
     * reverts that did succeed would be worse than reporting honestly.
     */
    public function bulkRevert(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer'],
        ]);

        $logs = ActivityLog::whereIn('id', $validated['ids'])
            ->orderByDesc('id')
            ->get();

        $reverted = 0;
        $blocked = 0;
        $failed = 0;

        foreach ($logs as $log) {
            $result = $this->changeLog->revert($log);

            if ($result->ok) {
                $reverted++;

                // Same housekeeping as the single revert: the section's quick
                // "undo last save" pointer has served its purpose.
                if ($section = $this->changeLog->sectionKey($log)) {
                    $this->changeLog->clearUndo($section);
                }

                continue;
            }

            $result->reason === RevertResult::REASON_CONFLICT ? $blocked++ : $failed++;
        }

        // Ids matching no row (deleted meanwhile) are counted as failures rather
        // than dropped, so the totals the admin reads always add up.
        $failed += count($validated['ids']) - $logs->count();

        if ($reverted === 0) {
            return back()->with('error', __('messages.admin.bulk_revert_none'));
        }

        $message = __('messages.admin.bulk_reverted', [
            'count' => $reverted,
            'total' => count($validated['ids']),
        ]);

        if ($blocked > 0 || $failed > 0) {
            $message .= ' '.__('messages.admin.bulk_revert_partial', [
                'blocked' => $blocked,
                'failed' => $failed,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Permanently delete several selected entries. Admin-only (route middleware).
     *
     * ⚠️ There is NO undo: activity_logs does not soft-delete, so the
     * type-to-confirm prompt is the only safety net.
     *
     * 🔴 SMACC stock-import entries are EXCLUDED and reported as skipped. The
     * Inventory page lists the last 10 of them and offers Undo on each, so
     * deleting one here would destroy that undo permanently with nothing on
     * either page explaining where it went.
     *
     * ⚠️ `reverts_log_id` is nullOnDelete, so deleting an entry that a later
     * revert points at silently strips that entry's "revert of #N" link. It
     * cannot fail or orphan a row, but the history reads thinner afterwards.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        // Partitioned in PHP rather than with a `!=` where clause: `action` is
        // compared by value and a SQL inequality would also drop NULL rows,
        // silently protecting entries this guard was never meant to cover.
        $ids = ActivityLog::whereIn('id', $validated['ids'])
            ->get(['id', 'action'])
            ->partition(fn (ActivityLog $log) => $log->action === SmaccImportService::ACTION);

        [$protected, $deletable] = [$ids[0], $ids[1]];

        $deleted = $deletable->isEmpty()
            ? 0
            : ActivityLog::whereIn('id', $deletable->pluck('id'))->delete();

        if ($deleted === 0) {
            return back()->with('error', __('messages.admin.bulk_delete_none'));
        }

        $message = __('messages.admin.bulk_logs_deleted', ['count' => $deleted]);

        if ($protected->isNotEmpty()) {
            $message .= ' '.__('messages.admin.bulk_delete_skipped_imports', ['count' => $protected->count()]);
        }

        return back()->with('success', $message);
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
