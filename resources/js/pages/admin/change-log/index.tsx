import Button from '@/components/admin/button';
import ConfirmDeleteButton from '@/components/admin/confirm-delete-button';
import ConfirmDialog from '@/components/admin/confirm-dialog';
import Pagination from '@/components/admin/pagination';
import StatusBadge from '@/components/admin/status-badge';
import StatusPill from '@/components/status-pill';
import { useCan } from '@/hooks/use-can';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { THEAD } from '@/lib/admin-ui';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, ExternalLink, RotateCcw, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface FieldChange {
    label: string;
    old: string;
    new: string;
}

interface LogRow {
    id: number;
    section: string;
    action: string;
    label: string | null;
    changes: FieldChange[];
    user: string | null;
    created_at: string | null;
    revertable: boolean;
    reverted_at: string | null;
    reverted_by: string | null;
    reverts_log_id: number | null;
    edit_url: string | null;
    fields: string[];
}

interface Paginated {
    data: LogRow[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

export default function ChangeLogIndex({ logs, highlight = null }: { logs: Paginated; highlight?: number | null }) {
    const { t } = useAdminT();
    const can = useCan();
    // Scroll to and briefly flag the entry linked from a conflict banner.
    const [flagged, setFlagged] = useState<number | null>(highlight);
    useEffect(() => {
        if (!highlight) return;
        setFlagged(highlight);
        document.getElementById(`log-${highlight}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const timer = setTimeout(() => setFlagged(null), 3500);
        return () => clearTimeout(timer);
    }, [highlight]);

    // Bulk selection. A Set keyed by id, deliberately NOT by index: the list
    // re-renders after every action, and indexes would silently come to point at
    // different rows.
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [confirmBulkRevert, setConfirmBulkRevert] = useState(false);

    // Selecting rows and then paging would submit ids the admin can no longer
    // see, so the selection is dropped whenever the visible page changes.
    // Returns the SAME set when already empty, so an ordinary re-render does not
    // churn state on every Inertia response (logs.data is a fresh array each time).
    useEffect(() => setSelected((prev) => (prev.size ? new Set() : prev)), [logs.data]);

    const toggle = (id: number) =>
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });

    const pageIds = logs.data.map((r) => r.id);
    const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.has(id));
    const toggleAll = () => setSelected(allSelected ? new Set() : new Set(pageIds));

    // Delete is ADMIN-ONLY and deliberately not a grantable permission: erasing
    // the record of who changed what stays with the owner. Mirrors the admin
    // check useCan makes; the route carries `admin` middleware, which is the real
    // enforcement, so this only avoids rendering a control that would 403.
    const isAdmin = usePage<{ auth?: { user?: { role?: string } } }>().props.auth?.user?.role === 'admin';

    const bulkRevert = () => {
        router.post('/admin/change-log/bulk-revert', { ids: [...selected] }, { preserveScroll: true });
        setSelected(new Set());
    };
    const bulkDelete = () => {
        router.delete('/admin/change-log/bulk-destroy', { data: { ids: [...selected] }, preserveScroll: true });
        setSelected(new Set());
    };

    const [confirmRow, setConfirmRow] = useState<LogRow | null>(null);
    const revert = (row: LogRow) => setConfirmRow(row);
    const doRevert = () => {
        if (!confirmRow) return;
        router.post(`/admin/change-log/${confirmRow.id}/revert`, {}, { preserveScroll: true });
    };

    return (
        <AdminLayout title={t('admin.changeLog.title')}>
            <Head title={t('admin.changeLog.title')} />

            {/* Shown only once something is selected, so the page is unchanged
                until the admin opts in. Sticky because the table is long and the
                actions have to stay reachable without scrolling back up. */}
            {selected.size > 0 && (
                <div className="border-brand-teal/40 bg-brand-teal/10 dark:bg-brand-teal/20 sticky top-2 z-10 mb-3 flex flex-wrap items-center gap-3 rounded-xl border px-4 py-2.5 backdrop-blur">
                    <span className="text-sm font-medium">{t('admin.changeLog.selected', { n: selected.size })}</span>
                    <div className="ms-auto flex flex-wrap items-center gap-2">
                        {can('change_log.revert') && (
                            <Button size="sm" variant="warning" icon={RotateCcw} onClick={() => setConfirmBulkRevert(true)}>
                                {t('admin.changeLog.revertSelected')}
                            </Button>
                        )}
                        {isAdmin && (
                            <ConfirmDeleteButton
                                onConfirm={bulkDelete}
                                label={t('admin.changeLog.deleteSelected')}
                                itemName={t('admin.changeLog.bulkDeleteItem', { n: selected.size })}
                            />
                        )}
                        <Button size="sm" variant="secondary" icon={X} onClick={() => setSelected(new Set())}>
                            {t('admin.changeLog.clearSelection')}
                        </Button>
                    </div>
                </div>
            )}

            <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                {logs.data.length === 0 ? (
                    <p className="p-6 text-sm text-neutral-400">{t('admin.changeLog.empty')}</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className={THEAD}>
                            <tr className="border-b border-neutral-100 dark:border-neutral-800">
                                <th className="w-10 py-3 ps-4 pe-0">
                                    <input
                                        type="checkbox"
                                        checked={allSelected}
                                        onChange={toggleAll}
                                        aria-label={t('admin.changeLog.selectAll')}
                                        className="h-4 w-4 cursor-pointer rounded border-neutral-300 dark:border-neutral-600"
                                    />
                                </th>
                                <th className="px-4 py-3 font-medium">{t('admin.changeLog.cols.when')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.changeLog.cols.section')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.changeLog.cols.action')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.changeLog.cols.item')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.changeLog.cols.changes')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.changeLog.cols.by')}</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((row) => (
                                <tr
                                    key={row.id}
                                    id={`log-${row.id}`}
                                    className={`border-b border-neutral-100 align-top transition-colors duration-1000 last:border-b-0 dark:border-neutral-800 ${
                                        flagged === row.id
                                            ? 'bg-amber-500/15 ring-2 ring-amber-500/60 ring-inset'
                                            : 'hover:bg-neutral-50 dark:hover:bg-neutral-800/30'
                                    }`}
                                >
                                    <td className="w-10 py-3 ps-4 pe-0">
                                        <input
                                            type="checkbox"
                                            checked={selected.has(row.id)}
                                            onChange={() => toggle(row.id)}
                                            aria-label={t('admin.changeLog.select')}
                                            className="h-4 w-4 cursor-pointer rounded border-neutral-300 dark:border-neutral-600"
                                        />
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-neutral-500">{row.created_at}</td>
                                    <td className="px-4 py-3 whitespace-nowrap">{row.section}</td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <StatusBadge domain="changeLog" value={row.action} />
                                        {row.reverts_log_id !== null && (
                                            <Link
                                                href={`/admin/change-log?highlight=${row.reverts_log_id}`}
                                                className="ms-1 rounded bg-neutral-100 px-1.5 py-0.5 text-[11px] text-neutral-500 transition-colors hover:bg-neutral-200 hover:text-neutral-700 dark:bg-neutral-800 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"
                                            >
                                                {t('admin.changeLog.revertOf', { id: row.reverts_log_id })}
                                            </Link>
                                        )}
                                    </td>
                                    <td className="max-w-40 truncate px-4 py-3">{row.label ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        {row.changes.length === 0 ? (
                                            <span className="text-neutral-400">—</span>
                                        ) : (
                                            <ul className="space-y-0.5">
                                                {row.changes.map((c, i) => (
                                                    <li key={i} className="text-xs" dir="auto">
                                                        <span className="text-neutral-500">{c.label}: </span>
                                                        <span className="text-red-500/70 line-through dark:text-red-400/70">{c.old || '—'}</span>
                                                        <span className="mx-1 text-neutral-400">→</span>
                                                        <span className="font-medium text-green-600 dark:text-green-400">{c.new || '—'}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">{row.user ?? '—'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <div className="flex items-center justify-end gap-2">
                                            {row.edit_url && (
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    icon={ExternalLink}
                                                    href={`${row.edit_url}${row.fields.length ? `?highlight=${row.fields.join(',')}` : ''}`}
                                                >
                                                    {t('admin.changeLog.open')}
                                                </Button>
                                            )}
                                            {row.reverted_at ? (
                                                <StatusPill
                                                    tone="idle"
                                                    icon={Check}
                                                    title={t('admin.changeLog.revertedTooltip', {
                                                        user: row.reverted_by ?? '—',
                                                        at: row.reverted_at,
                                                    })}
                                                >
                                                    {t('admin.changeLog.reverted')}
                                                </StatusPill>
                                            ) : row.revertable && can('change_log.revert') ? (
                                                <Button size="sm" variant="danger" icon={RotateCcw} onClick={() => revert(row)}>
                                                    {t('admin.changeLog.revert')}
                                                </Button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {/* Was a byte-for-byte copy of the shared pager's markup. */}
            <Pagination paginator={logs} />

            <ConfirmDialog
                open={confirmBulkRevert}
                onClose={() => setConfirmBulkRevert(false)}
                onConfirm={bulkRevert}
                title={t('admin.changeLog.revertSelected')}
                message={t('admin.changeLog.bulkRevertConfirm', { n: selected.size })}
                confirmLabel={t('admin.changeLog.revertSelected')}
                confirmVariant="warning"
                icon={RotateCcw}
            />

            <ConfirmDialog
                open={!!confirmRow}
                onClose={() => setConfirmRow(null)}
                onConfirm={doRevert}
                title={t('admin.changeLog.revert')}
                message={t('admin.changeLog.revertConfirm')}
                confirmLabel={t('admin.changeLog.revert')}
                confirmVariant="warning"
                icon={RotateCcw}
            />
        </AdminLayout>
    );
}
