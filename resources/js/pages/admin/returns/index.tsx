import { Head, Link, router } from '@inertiajs/react';
import { Columns3, MoveHorizontal } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import ExportButtons from '@/components/admin/export-buttons';
import ResizableTh from '@/components/admin/resizable-th';
import StickyScrollWrapper from '@/components/admin/sticky-scroll-wrapper';
import { useResizableColumns, type ColumnDef } from '@/hooks/use-resizable-columns';
import { useAdminT } from '@/i18n/use-admin-t';

const COLUMNS: ColumnDef[] = [
    { key: 'return', defaultWidth: 100, minWidth: 70 },
    { key: 'order', defaultWidth: 150, minWidth: 100 },
    { key: 'customer', defaultWidth: 170, minWidth: 110 },
    { key: 'status', defaultWidth: 120, minWidth: 90 },
    { key: 'reason', defaultWidth: 260, minWidth: 140 },
    { key: 'filed', defaultWidth: 160, minWidth: 120 },
];

interface ReturnRow {
    id: number;
    order_number: string | null;
    customer: string | null;
    status: string;
    reason: string;
    created_at: string | null;
}

interface Filters {
    status: string | null;
    sort: string | null;
    direction: 'asc' | 'desc';
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

export default function ReturnsIndex({
    returns,
    filters,
    statuses,
    counts,
}: {
    returns: Paginator<ReturnRow>;
    filters: Filters;
    statuses: string[];
    counts: Record<string, number>;
}) {
    const { t } = useAdminT();
    const rc = useResizableColumns({ tableKey: 'returns', columns: COLUMNS });

    const query = (next: Record<string, unknown>) => {
        router.get(
            '/admin/returns',
            {
                status: filters.status || undefined,
                sort: filters.sort || undefined,
                direction: filters.sort ? filters.direction : undefined,
                ...next,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const filterBy = (status: string | null) => query({ status: status || undefined });

    const toggleSort = (col: string) => {
        const direction = filters.sort === col && filters.direction === 'asc' ? 'desc' : 'asc';
        query({ sort: col, direction });
    };

    const exportParams = {
        status: filters.status,
        sort: filters.sort,
        direction: filters.sort ? filters.direction : undefined,
    };

    return (
        <AdminLayout title={t('admin.returns.title')}>
            <Head title={t('admin.returns.title')} />

            <div className="mb-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    onClick={() => filterBy(null)}
                    className={`rounded-full px-3 py-1 text-sm ${!filters.status ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300'}`}
                >
                    {t('admin.common.all')}
                </button>
                {statuses.map((s) => (
                    <button
                        key={s}
                        type="button"
                        onClick={() => filterBy(s)}
                        className={`rounded-full px-3 py-1 text-sm ${filters.status === s ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300'}`}
                    >
                        {t(`admin.returns.status.${s}`)}
                        {counts[s] ? ` (${counts[s]})` : ''}
                    </button>
                ))}
            </div>

            <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm text-neutral-400">{t('admin.returns.count', { n: returns.total })}</span>
                    {rc.isDefault ? (
                        <span className="hidden items-center gap-1.5 text-xs text-neutral-500 lg:inline-flex">
                            <MoveHorizontal className="h-3.5 w-3.5" /> {t('admin.common.dragToResize')}
                        </span>
                    ) : (
                        <Button size="sm" variant="ghost" icon={Columns3} onClick={rc.resetAll}>{t('admin.common.resetColumns')}</Button>
                    )}
                </div>
                <ExportButtons base="/admin/returns/export" params={exportParams} />
            </div>

            <StickyScrollWrapper className="rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="min-w-full table-fixed text-sm" style={{ width: rc.tableWidth }}>
                    <thead className="border-b border-neutral-200 bg-neutral-50 text-left text-neutral-600 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-300">
                        <tr>
                            <ResizableTh colKey="return" width={rc.widths.return} resizeProps={rc.getResizeHandleProps('return')} resizing={rc.resizing === 'return'} sortKey="id" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.returns.cols.return')}</ResizableTh>
                            <ResizableTh colKey="order" width={rc.widths.order} resizeProps={rc.getResizeHandleProps('order')} resizing={rc.resizing === 'order'} sortKey="order_number" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.returns.cols.order')}</ResizableTh>
                            <ResizableTh colKey="customer" width={rc.widths.customer} resizeProps={rc.getResizeHandleProps('customer')} resizing={rc.resizing === 'customer'} sortKey="customer_name" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.returns.cols.customer')}</ResizableTh>
                            <ResizableTh colKey="status" width={rc.widths.status} resizeProps={rc.getResizeHandleProps('status')} resizing={rc.resizing === 'status'} sortKey="status" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.returns.cols.status')}</ResizableTh>
                            <ResizableTh colKey="reason" width={rc.widths.reason} resizeProps={rc.getResizeHandleProps('reason')} resizing={rc.resizing === 'reason'}>{t('admin.returns.cols.reason')}</ResizableTh>
                            <ResizableTh colKey="filed" width={rc.widths.filed} resizeProps={rc.getResizeHandleProps('filed')} resizing={rc.resizing === 'filed'} sortKey="created_at" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.returns.cols.filed')}</ResizableTh>
                        </tr>
                    </thead>
                    <tbody>
                        {returns.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-neutral-400">
                                    {t('admin.returns.empty')}
                                </td>
                            </tr>
                        )}
                        {returns.data.map((r) => (
                            <tr key={r.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td className="px-4 py-3">
                                    <Link href={`/admin/returns/${r.id}`} className="block truncate font-mono text-blue-600 underline dark:text-blue-400">
                                        #{r.id}
                                    </Link>
                                </td>
                                <td className="truncate px-4 py-3 font-mono">{r.order_number ?? '—'}</td>
                                <td className="truncate px-4 py-3">{r.customer ?? '—'}</td>
                                <td className="truncate px-4 py-3">{t(`admin.returns.status.${r.status}`)}</td>
                                <td className="truncate px-4 py-3 text-neutral-500" dir="auto">{r.reason}</td>
                                <td className="truncate px-4 py-3 text-neutral-500">{r.created_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </StickyScrollWrapper>

            {returns.total > returns.data.length && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {returns.links.map((link, i) => (
                        <button
                            key={i}
                            type="button"
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                            className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-600 disabled:opacity-40 dark:text-neutral-300'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
