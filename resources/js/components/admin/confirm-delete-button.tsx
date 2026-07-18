import { AlertTriangle, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Button from '@/components/admin/button';
import Modal from '@/components/admin/modal';
import { useAdminT } from '@/i18n/use-admin-t';

/**
 * Guarded delete trigger (inspired by Sky Amman's ConfirmDeleteButton): a danger
 * button that opens a modal requiring the admin to TYPE a confirm word before the
 * delete enables — so a stray double-click can't delete anything. Set
 * `reversible` when the deletion can be undone from the change log.
 */
export default function ConfirmDeleteButton({
    onConfirm,
    itemName,
    reversible = false,
    size = 'sm',
    label,
}: {
    onConfirm: () => void;
    itemName?: string;
    reversible?: boolean;
    size?: 'sm' | 'md';
    label?: string;
}) {
    const { t } = useAdminT();
    const confirmWord = t('admin.deleteModal.confirmWord'); // localized: "delete" / "احذف"
    const [open, setOpen] = useState(false);
    const [text, setText] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (!open) return;
        setText('');
        const id = setTimeout(() => inputRef.current?.focus(), 60);
        return () => clearTimeout(id);
    }, [open]);

    const ready = text.trim().toLowerCase() === confirmWord.toLowerCase();

    const confirm = () => {
        if (!ready) return;
        setOpen(false);
        onConfirm();
    };

    return (
        <>
            <Button size={size} variant="danger" icon={Trash2} onClick={() => setOpen(true)}>
                {label ?? t('admin.common.delete')}
            </Button>

            <Modal open={open} onClose={() => setOpen(false)} size="sm" title={t('admin.deleteModal.title')}>
                <div className="space-y-4">
                    <div className="flex items-start gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-500/15 text-red-600 dark:text-red-400">
                            <AlertTriangle className="h-5 w-5" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm text-neutral-700 dark:text-neutral-200" dir="auto">
                                {t('admin.deleteModal.lead', { name: itemName ?? '' })}
                            </p>
                            <p className={`mt-1 text-xs ${reversible ? 'text-neutral-500 dark:text-neutral-400' : 'font-medium text-red-600 dark:text-red-400'}`}>
                                {reversible ? t('admin.deleteModal.reversible') : t('admin.deleteModal.permanent')}
                            </p>
                        </div>
                    </div>

                    <label className="block">
                        <span className="text-sm text-neutral-600 dark:text-neutral-300">{t('admin.deleteModal.prompt', { word: confirmWord })}</span>
                        <input
                            ref={inputRef}
                            dir="auto"
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && confirm()}
                            placeholder={confirmWord}
                            autoComplete="off"
                            className="mt-1 w-full rounded-lg border border-red-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500/40 dark:border-red-900 dark:bg-neutral-950"
                        />
                    </label>

                    <div className="flex justify-end gap-2 pt-1">
                        <Button variant="secondary" onClick={() => setOpen(false)}>{t('admin.common.cancel')}</Button>
                        <Button variant="danger" icon={Trash2} disabled={!ready} onClick={confirm}>{t('admin.deleteModal.confirm')}</Button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
