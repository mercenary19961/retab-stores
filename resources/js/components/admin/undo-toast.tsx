import { router, usePage } from '@inertiajs/react';
import { History, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { UndoMeta } from './undo-button';

/**
 * Transient toast shown once, immediately after a tracked admin save (driven by
 * the flashed `undo` shared prop). Offers a one-click revert; the persistent
 * per-section UndoButton covers undo after navigating away.
 */
export default function UndoToast() {
    const { t } = useTranslation();
    const undo = (usePage().props as { undo?: UndoMeta | null }).undo ?? null;
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (!undo) return;
        setVisible(true);
        const timer = setTimeout(() => setVisible(false), 6000);
        return () => clearTimeout(timer);
    }, [undo?.id]);

    if (!undo || !visible) return null;

    const revert = () => {
        setVisible(false);
        router.post(`/admin/change-log/${undo.id}/revert`, {}, { preserveScroll: true });
    };

    return (
        <div className="fixed bottom-6 end-6 z-50 flex items-center gap-3 rounded-lg border border-neutral-700 bg-neutral-900 px-4 py-3 text-sm text-neutral-200 shadow-xl">
            <History className="h-4 w-4 shrink-0 text-brand-gold" />
            <span dir="auto">{t('admin.undo.saved', { label: undo.label })}</span>
            <button type="button" onClick={revert} className="font-semibold text-brand-gold hover:underline">
                {t('admin.undo.button')}
            </button>
            <button
                type="button"
                onClick={() => setVisible(false)}
                aria-label={t('admin.undo.dismiss')}
                className="text-neutral-500 hover:text-neutral-300"
            >
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}
