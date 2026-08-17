import { router } from '@inertiajs/react';
import { History, X } from 'lucide-react';
import { useState } from 'react';

import ConfirmDialog from '@/components/admin/confirm-dialog';
import { useAdminT } from '@/i18n/use-admin-t';
import { relativeTimeFromIso } from '@/lib/relative-time';

export interface UndoMeta {
    id: number;
    section: string;
    action: string;
    label: string;
    changes: { label: string; old: string; new: string }[];
    at: string | null;
}

const MAX_SHOWN = 8;

/**
 * The field diff, rendered identically in the hover preview and in the confirm
 * dialog — one renderer so the two can never drift apart and show a different
 * picture of the same pending change.
 *
 * ⚠️ `onDark` is needed because the two surfaces have different backgrounds: the
 * hover panel is hardcoded dark whatever the admin theme, while the dialog
 * follows it. Without the split, the old/new colours would sit at poor contrast
 * on one of them.
 */
function ChangeDiff({ changes, onDark = false }: { changes: UndoMeta['changes']; onDark?: boolean }) {
    const muted = onDark ? 'text-neutral-500' : 'text-neutral-500 dark:text-neutral-400';
    const removed = onDark ? 'text-red-400' : 'text-red-600 dark:text-red-400';
    const added = onDark ? 'text-green-400' : 'text-green-700 dark:text-green-400';

    return (
        <ul className="space-y-1">
            {changes.slice(0, MAX_SHOWN).map((c, i) => (
                <li key={i} dir="auto">
                    <span className={muted}>{c.label}: </span>
                    <span className={`${removed} line-through`}>{c.old || '—'}</span>
                    <span className={muted}> → </span>
                    <span className={added}>{c.new || '—'}</span>
                </li>
            ))}
            {changes.length > MAX_SHOWN && <li className={muted}>…</li>}
        </ul>
    );
}

/**
 * Persistent "Undo last save" affordance for a section. Backed by a session
 * pointer (survives navigation until reverted or dismissed) and reuses the
 * change-log revert route, so the same conflict-checked machinery applies.
 * Hover reveals the field diff.
 */
export default function UndoButton({ section, undoMeta }: { section: string; undoMeta: UndoMeta | null }) {
    // useAdminT, not a plain useTranslation: this renders inside the admin panel,
    // where a plain hook resolves against whichever i18n instance happens to be
    // above it in the tree (the storefront's Arabic one, if the provider is not).
    // It also matches ConfirmDialog below, so the dialog can't end up half
    // translated by one instance and half by the other.
    const { t, i18n } = useAdminT();
    const [confirming, setConfirming] = useState(false);

    if (!undoMeta) return null;

    const revert = () => {
        router.post(`/admin/change-log/${undoMeta.id}/revert`, {}, { preserveScroll: true });
    };

    const dismiss = () => router.delete(`/admin/change-log/undo/${section}`, { preserveScroll: true, preserveState: true });

    return (
        <div className="group/undo border-brand-gold/40 bg-brand-gold/10 text-brand-gold relative inline-flex items-center gap-1 rounded-lg border py-1.5 ps-3 pe-1.5 text-sm">
            <button type="button" data-undo onClick={() => setConfirming(true)} className="flex items-center gap-2 font-medium">
                <History className="h-4 w-4" />
                <span>{t('admin.undo.button')}</span>
                {undoMeta.changes.length > 0 && <span className="text-xs opacity-70">({undoMeta.changes.length})</span>}
            </button>
            <button type="button" onClick={dismiss} aria-label={t('admin.undo.dismiss')} className="rounded p-0.5 opacity-70 hover:opacity-100">
                <X className="h-3.5 w-3.5" />
            </button>

            {/* Suppressed while the dialog is open: the pointer is still over the
                button that opened it, so the hover panel would stay up and leak
                the same diff around the edges of the modal. */}
            {undoMeta.changes.length > 0 && !confirming && (
                <div className="pointer-events-none absolute top-full z-40 mt-2 hidden w-72 rounded-lg border border-neutral-700 bg-neutral-900 p-3 text-xs text-neutral-300 shadow-xl group-hover/undo:block">
                    <p className="mb-2 font-semibold text-neutral-100" dir="auto">
                        {undoMeta.label}
                    </p>
                    <ChangeDiff changes={undoMeta.changes} onDark />
                </div>
            )}

            <ConfirmDialog
                open={confirming}
                onClose={() => setConfirming(false)}
                onConfirm={revert}
                title={t('admin.undo.button')}
                message={t('admin.undo.confirm')}
                /*
                 * Names WHAT would be reverted, so the confirmation carries
                 * information rather than just costing a click. The diff used to
                 * live only in the hover panel above — unreachable on touch, and
                 * invisible to anyone who opened this dialog by mis-clicking, i.e.
                 * exactly the person who most needs to read it.
                 */
                details={
                    <div className="rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-xs dark:border-neutral-700 dark:bg-neutral-800/60">
                        <p className="font-semibold text-neutral-800 dark:text-neutral-100" dir="auto">
                            {undoMeta.label}
                        </p>
                        {undoMeta.at && (
                            <p className="mt-0.5 text-neutral-500 dark:text-neutral-400">
                                {t('admin.undo.savedAgo', { when: relativeTimeFromIso(undoMeta.at, i18n.language) })}
                            </p>
                        )}
                        {undoMeta.changes.length > 0 && (
                            <div className="mt-2 border-t border-neutral-200 pt-2 dark:border-neutral-700">
                                <ChangeDiff changes={undoMeta.changes} />
                            </div>
                        )}
                    </div>
                }
                confirmLabel={t('admin.undo.button')}
                icon={History}
                confirmVariant="warning"
            />
        </div>
    );
}
