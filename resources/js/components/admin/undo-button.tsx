import { router } from '@inertiajs/react';
import { History, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export interface UndoMeta {
    id: number;
    section: string;
    action: string;
    label: string;
    changes: { label: string; old: string; new: string }[];
    at: string | null;
}

/**
 * Persistent "Undo last save" affordance for a section. Backed by a session
 * pointer (survives navigation until reverted or dismissed) and reuses the
 * change-log revert route, so the same conflict-checked machinery applies.
 * Hover reveals the field diff.
 */
export default function UndoButton({ section, undoMeta }: { section: string; undoMeta: UndoMeta | null }) {
    const { t } = useTranslation();

    if (!undoMeta) return null;

    const revert = () => {
        if (!window.confirm(t('admin.undo.confirm'))) return;
        router.post(`/admin/change-log/${undoMeta.id}/revert`, {}, { preserveScroll: true });
    };

    const dismiss = () =>
        router.delete(`/admin/change-log/undo/${section}`, { preserveScroll: true, preserveState: true });

    return (
        <div className="group/undo relative inline-flex items-center gap-1 rounded-lg border border-brand-gold/40 bg-brand-gold/10 py-1.5 pe-1.5 ps-3 text-sm text-brand-gold">
            <button type="button" onClick={revert} className="flex items-center gap-2 font-medium">
                <History className="h-4 w-4" />
                <span>{t('admin.undo.button')}</span>
                {undoMeta.changes.length > 0 && <span className="text-xs opacity-70">({undoMeta.changes.length})</span>}
            </button>
            <button
                type="button"
                onClick={dismiss}
                aria-label={t('admin.undo.dismiss')}
                className="rounded p-0.5 opacity-70 hover:opacity-100"
            >
                <X className="h-3.5 w-3.5" />
            </button>

            {undoMeta.changes.length > 0 && (
                <div className="pointer-events-none absolute top-full z-40 mt-2 hidden w-72 rounded-lg border border-neutral-700 bg-neutral-900 p-3 text-xs text-neutral-300 shadow-xl group-hover/undo:block">
                    <p className="mb-2 font-semibold text-neutral-100" dir="auto">{undoMeta.label}</p>
                    <ul className="space-y-1">
                        {undoMeta.changes.slice(0, 8).map((c, i) => (
                            <li key={i} dir="auto">
                                <span className="text-neutral-500">{c.label}: </span>
                                <span className="text-red-400 line-through">{c.old || '—'}</span>
                                <span className="text-neutral-500"> → </span>
                                <span className="text-green-400">{c.new || '—'}</span>
                            </li>
                        ))}
                        {undoMeta.changes.length > 8 && <li className="text-neutral-500">…</li>}
                    </ul>
                </div>
            )}
        </div>
    );
}
