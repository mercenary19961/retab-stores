import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface RevertConflict {
    fields: string[];
    blockerId: number | null;
    blockerLabel: string | null;
}

/**
 * Shown after a revert is refused because a later edit touched the same field(s).
 * Names the blocked fields and links to the change that must be undone first —
 * the change-log page then scrolls to and highlights it.
 */
export default function RevertConflictBanner() {
    const { t } = useTranslation();
    const conflict = (usePage().props as { flash?: { revertConflict?: RevertConflict | null } }).flash?.revertConflict ?? null;

    if (!conflict) return null;

    return (
        <div className="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            <AlertTriangle className="h-4 w-4 shrink-0" />
            <span dir="auto">{t('admin.revertConflict.message', { fields: conflict.fields.join(', ') })}</span>
            {conflict.blockerId && (
                <Link
                    href={`/admin/change-log?highlight=${conflict.blockerId}`}
                    className="font-semibold text-amber-100 underline underline-offset-2 hover:text-white"
                >
                    {t('admin.revertConflict.goToBlocker', { label: conflict.blockerLabel ?? '' })}
                </Link>
            )}
        </div>
    );
}
