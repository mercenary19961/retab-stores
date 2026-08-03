import { router } from '@inertiajs/react';
import { History, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import ConfirmDialog from '@/components/admin/confirm-dialog';

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

            {undoMeta.changes.length > 0 && (
                <div className="pointer-events-none absolute top-full z-40 mt-2 hidden w-72 rounded-lg border border-neutral-700 bg-neutral-900 p-3 text-xs text-neutral-300 shadow-xl group-hover/undo:block">
                    <p className="mb-2 font-semibold text-neutral-100" dir="auto">
                        {undoMeta.label}
                    </p>
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

            <ConfirmDialog
                open={confirming}
                onClose={() => setConfirming(false)}
                onConfirm={revert}
                title={t('admin.undo.button')}
                message={t('admin.undo.confirm')}
                confirmLabel={t('admin.undo.button')}
                icon={History}
                confirmVariant="warning"
            />
        </div>
    );
}
