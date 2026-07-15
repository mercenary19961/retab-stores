import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import ExportButtons from '@/components/admin/export-buttons';
import SortableTh from '@/components/admin/sortable-th';

const STATUS_LABELS: Record<string, string> = {
    requested: 'Requested',
    approved: 'Approved',
    rejected: 'Rejected',
    exchanged: 'Exchanged',
    refunded: 'Refunded',
};

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
        <AdminLayout title="Returns">
            <Head title="Returns" />

            <div className="mb-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    onClick={() => filterBy(null)}
                    className={`rounded-full px-3 py-1 text-sm ${!filters.status ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300'}`}
                >
                    All
                </button>
                {statuses.map((s) => (
                    <button
                        key={s}
                        type="button"
                        onClick={() => filterBy(s)}
                        className={`rounded-full px-3 py-1 text-sm ${filters.status === s ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300'}`}
                    >
                        {STATUS_LABELS[s] ?? s}
                        {counts[s] ? ` (${counts[s]})` : ''}
                    </button>
                ))}
            </div>

            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <span className="text-sm text-neutral-400">{returns.total} returns</span>
                <ExportButtons base="/admin/returns/export" params={exportParams} />
            </div>

            <div className="overflow-x-auto rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-sm">
                    <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <SortableTh col="id" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Return</SortableTh>
                            <SortableTh col="order_number" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Order</SortableTh>
                            <SortableTh col="customer_name" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Customer</SortableTh>
                            <SortableTh col="status" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Status</SortableTh>
                            <th className="px-4 py-3 font-medium">Reason</th>
                            <SortableTh col="created_at" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Filed</SortableTh>
                        </tr>
                    </thead>
                    <tbody>
                        {returns.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-neutral-400">
                                    No returns.
                                </td>
                            </tr>
                        )}
                        {returns.data.map((r) => (
                            <tr key={r.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td className="px-4 py-3">
                                    <Link href={`/admin/returns/${r.id}`} className="font-mono text-blue-600 underline dark:text-blue-400">
                                        #{r.id}
                                    </Link>
                                </td>
                                <td className="px-4 py-3 font-mono">{r.order_number ?? '—'}</td>
                                <td className="px-4 py-3">{r.customer ?? '—'}</td>
                                <td className="px-4 py-3">{STATUS_LABELS[r.status] ?? r.status}</td>
                                <td className="px-4 py-3 text-neutral-500" dir="auto">{r.reason}</td>
                                <td className="px-4 py-3 text-neutral-500">{r.created_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

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
