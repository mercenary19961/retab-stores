import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import OrderStatusBadge, { ORDER_STATUS_LABELS } from '@/components/order-status-badge';

interface OrderRow {
    order_number: string;
    customer_name: string | null;
    status: string;
    payment_status: string;
    payment_method: string | null;
    total: number;
    created_at: string | null;
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}

export default function OrdersIndex({
    orders,
    filters,
    statuses,
    counts,
}: {
    orders: Paginator<OrderRow>;
    filters: { status: string | null };
    statuses: string[];
    counts: Record<string, number>;
}) {
    const filterBy = (status: string | null) => {
        router.get('/admin/orders', status ? { status } : {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdminLayout title="Orders">
            <Head title="Orders" />

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
                        {ORDER_STATUS_LABELS[s] ?? s}
                        {counts[s] ? ` (${counts[s]})` : ''}
                    </button>
                ))}
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-sm">
                    <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Order</th>
                            <th className="px-4 py-3 font-medium">Customer</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Payment</th>
                            <th className="px-4 py-3 font-medium">Total</th>
                            <th className="px-4 py-3 font-medium">Placed</th>
                        </tr>
                    </thead>
                    <tbody>
                        {orders.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-neutral-400">
                                    No orders.
                                </td>
                            </tr>
                        )}
                        {orders.data.map((order) => (
                            <tr
                                key={order.order_number}
                                className="border-b border-neutral-100 last:border-0 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/50"
                            >
                                <td className="px-4 py-3">
                                    <Link href={`/admin/orders/${order.order_number}`} className="font-mono font-medium text-blue-600 hover:underline dark:text-blue-400">
                                        {order.order_number}
                                    </Link>
                                </td>
                                <td className="px-4 py-3">{order.customer_name ?? '—'}</td>
                                <td className="px-4 py-3"><OrderStatusBadge status={order.status} /></td>
                                <td className="px-4 py-3 text-neutral-500">
                                    {order.payment_method ?? '—'} · {order.payment_status}
                                </td>
                                <td className="px-4 py-3">{order.total} SAR</td>
                                <td className="px-4 py-3 text-neutral-500">{order.created_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {orders.total > orders.data.length && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {orders.links.map((link, i) => (
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
