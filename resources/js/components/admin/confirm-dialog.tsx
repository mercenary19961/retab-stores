import { type LucideIcon } from 'lucide-react';

import Button from '@/components/admin/button';
import Modal from '@/components/admin/modal';
import { useAdminT } from '@/i18n/use-admin-t';

/**
 * A lightweight styled yes/no confirmation dialog — the in-UI replacement for
 * window.confirm(). Controlled via `open`; runs `onConfirm` then closes. Use for
 * quick, reversible actions (undo / revert); ConfirmDeleteButton stays the guard
 * for destructive deletes (it makes you type a word).
 */
export default function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title,
    message,
    confirmLabel,
    confirmVariant = 'primary',
    icon,
}: {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    message: string;
    confirmLabel?: string;
    confirmVariant?: 'primary' | 'danger' | 'warning';
    icon?: LucideIcon;
}) {
    const { t } = useAdminT();

    const confirm = () => {
        onClose();
        onConfirm();
    };

    return (
        <Modal open={open} onClose={onClose} size="sm" title={title}>
            <div className="space-y-4">
                <p className="text-sm text-neutral-700 dark:text-neutral-200" dir="auto">
                    {message}
                </p>
                <div className="flex justify-end gap-2 pt-1">
                    <Button variant="secondary" onClick={onClose}>
                        {t('admin.common.cancel')}
                    </Button>
                    <Button variant={confirmVariant} icon={icon} onClick={confirm}>
                        {confirmLabel ?? t('admin.common.confirm')}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
