import { X } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';
import { useAdminT } from '@/i18n/use-admin-t';

/**
 * A centered modal dialog for the admin panel. Closes on backdrop click or Esc.
 * Render conditionally by passing `open`; children are the dialog body.
 */
export default function Modal({
    open,
    onClose,
    title,
    size = 'md',
    children,
}: {
    open: boolean;
    onClose: () => void;
    title?: ReactNode;
    size?: 'md' | 'lg';
    children: ReactNode;
}) {
    const { t } = useAdminT();

    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-6">
            <div className="fixed inset-0 bg-black/50" onClick={onClose} aria-hidden="true" />
            <div
                role="dialog"
                aria-modal="true"
                className={`relative z-10 my-6 w-full rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-900 ${
                    size === 'lg' ? 'max-w-4xl' : 'max-w-2xl'
                }`}
            >
                <div className="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4 dark:border-neutral-800">
                    <h2 className="min-w-0 truncate font-semibold text-neutral-900 dark:text-neutral-100">{title}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label={t('admin.common.close')}
                        className="shrink-0 rounded p-1 text-neutral-400 transition-colors hover:text-neutral-700 dark:hover:text-neutral-200"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <div className="px-5 py-5">{children}</div>
            </div>
        </div>
    );
}
