import { Head, Link, router } from '@inertiajs/react';
import { Columns3, MoveHorizontal } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import OrderStatusBadge, { ORDER_STATUS_LABELS } from '@/components/order-status-badge';
import ExportButtons from '@/components/admin/export-buttons';
import ResizableTh from '@/components/admin/resizable-th';
import StickyScrollWrapper from '@/components/admin/sticky-scroll-wrapper';
import { useResizableColumns, type ColumnDef } from '@/hooks/use-resizable-columns';

const COLUMNS: ColumnDef[] = [
    { key: 'order', defaultWidth: 170, minWidth: 120 },
    { key: 'customer', defaultWidth: 180, minWidth: 110 },
    { key: 'status', defaultWidth: 140, minWidth: 100 },
    { key: 'payment', defaultWidth: 190, minWidth: 120 },
    { key: 'total', defaultWidth: 110, minWidth: 80 },
    { key: 'placed', defaultWidth: 170, minWidth: 120 },
];

interface OrderRow {
    order_number: string;
    customer_name: string | null;
    status: string;
    payment_status: string;
    payment_method: string | null;
    total: number;
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
    filters: Filters;
    statuses: string[];
    counts: Record<string, number>;
}) {
    const rc = useResizableColumns({ tableKey: 'orders', columns: COLUMNS });

    const query = (next: Record<string, unknown>) => {
        router.get(
            '/admin/orders',
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

            <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm text-neutral-400">{orders.total} orders</span>
                    {rc.isDefault ? (
                        <span className="hidden items-center gap-1.5 text-xs text-neutral-500 lg:inline-flex">
                            <MoveHorizontal className="h-3.5 w-3.5" /> Drag column edges to resize
                        </span>
                    ) : (
                        <Button size="sm" variant="ghost" icon={Columns3} onClick={rc.resetAll}>Reset columns</Button>
                    )}
                </div>
                <ExportButtons base="/admin/orders/export" params={exportParams} />
            </div>

            <StickyScrollWrapper className="rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="min-w-full table-fixed text-sm" style={{ width: rc.tableWidth }}>
                    <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <ResizableTh colKey="order" width={rc.widths.order} resizeProps={rc.getResizeHandleProps('order')} resizing={rc.resizing === 'order'} sortKey="order_number" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Order</ResizableTh>
                            <ResizableTh colKey="customer" width={rc.widths.customer} resizeProps={rc.getResizeHandleProps('customer')} resizing={rc.resizing === 'customer'} sortKey="customer_name" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Customer</ResizableTh>
                            <ResizableTh colKey="status" width={rc.widths.status} resizeProps={rc.getResizeHandleProps('status')} resizing={rc.resizing === 'status'} sortKey="status" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Status</ResizableTh>
                            <ResizableTh colKey="payment" width={rc.widths.payment} resizeProps={rc.getResizeHandleProps('payment')} resizing={rc.resizing === 'payment'} sortKey="payment_status" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Payment</ResizableTh>
                            <ResizableTh colKey="total" width={rc.widths.total} resizeProps={rc.getResizeHandleProps('total')} resizing={rc.resizing === 'total'} sortKey="total" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Total</ResizableTh>
                            <ResizableTh colKey="placed" width={rc.widths.placed} resizeProps={rc.getResizeHandleProps('placed')} resizing={rc.resizing === 'placed'} sortKey="created_at" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Placed</ResizableTh>
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
                                    <Link href={`/admin/orders/${order.order_number}`} className="block truncate font-mono font-medium text-blue-600 hover:underline dark:text-blue-400">
                                        {order.order_number}
                                    </Link>
                                </td>
                                <td className="truncate px-4 py-3" dir="auto">{order.customer_name ?? '—'}</td>
                                <td className="px-4 py-3"><OrderStatusBadge status={order.status} /></td>
                                <td className="truncate px-4 py-3 text-neutral-500">
                                    {order.payment_method ?? '—'} · {order.payment_status}
                                </td>
                                <td className="px-4 py-3">{order.total} SAR</td>
                                <td className="truncate px-4 py-3 text-neutral-500">{order.created_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </StickyScrollWrapper>

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
