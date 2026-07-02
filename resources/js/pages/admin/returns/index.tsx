import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';

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
    filters: { status: string | null };
    statuses: string[];
    counts: Record<string, number>;
}) {
    const filterBy = (status: string | null) => {
        router.get('/admin/returns', status ? { status } : {}, { preserveState: true, preserveScroll: true });
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

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-sm">
                    <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Return</th>
                            <th className="px-4 py-3 font-medium">Order</th>
                            <th className="px-4 py-3 font-medium">Customer</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Reason</th>
                            <th className="px-4 py-3 font-medium">Filed</th>
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
        </AdminLayout>
    );
}
