import { Head, router } from '@inertiajs/react';
import { Columns3, Eye, MoveHorizontal } from 'lucide-react';
import { useEffect, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import OrderStatusBadge from '@/components/order-status-badge';
import ExportButtons from '@/components/admin/export-buttons';
import Modal from '@/components/admin/modal';
import OrderDetailView, { type OrderCan, type OrderDetailData } from '@/components/admin/order-detail-view';
import PaymentStatusBadge from '@/components/admin/payment-status-badge';
import ResizableTh from '@/components/admin/resizable-th';
import StickyScrollWrapper from '@/components/admin/sticky-scroll-wrapper';
import { useResizableColumns, type ColumnDef } from '@/hooks/use-resizable-columns';
import { useAdminT } from '@/i18n/use-admin-t';

const COLUMNS: ColumnDef[] = [
    { key: 'order', defaultWidth: 170, minWidth: 120 },
    { key: 'customer', defaultWidth: 180, minWidth: 110 },
    { key: 'status', defaultWidth: 140, minWidth: 100 },
    { key: 'payment', defaultWidth: 190, minWidth: 120 },
    { key: 'total', defaultWidth: 110, minWidth: 80 },
    { key: 'placed', defaultWidth: 170, minWidth: 120 },
    { key: 'actions', defaultWidth: 120, minWidth: 90 },
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

// Modal body: fetches the order, drives its lifecycle actions, and re-fetches
// after each (Inertia handles CSRF and refreshes the list behind the modal).
function OrderDetail({ orderNumber }: { orderNumber: string }) {
    const { t } = useAdminT();
    const [data, setData] = useState<{ order: OrderDetailData; can: OrderCan } | null>(null);
    const [failed, setFailed] = useState(false);
    const [busy, setBusy] = useState(false);
    const [reload, setReload] = useState(0);

    useEffect(() => {
        let alive = true;
        fetch(`/admin/orders/${orderNumber}/detail`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => {
                if (!r.ok) throw new Error();
                return r.json();
            })
            .then((d: { order: OrderDetailData; can: OrderCan }) => alive && setData(d))
            .catch(() => alive && setFailed(true));
        return () => { alive = false; };
    }, [orderNumber, reload]);

    const action = (verb: string, actionData: Record<string, string> = {}, confirmMsg?: string) => {
        if (confirmMsg && !window.confirm(confirmMsg)) return;
        router.post(`/admin/orders/${orderNumber}/${verb}`, actionData, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setBusy(true),
            onSuccess: () => setReload((n) => n + 1),
            onFinish: () => setBusy(false),
        });
    };

    if (failed) return <p className="py-6 text-sm text-red-500">{t('admin.orders.detailLoadError')}</p>;
    if (!data) return <p className="py-8 text-center text-sm text-neutral-400">{t('admin.common.loading')}</p>;

    return <OrderDetailView order={data.order} can={data.can} onAction={action} busy={busy} />;
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
    const { t } = useAdminT();
    const rc = useResizableColumns({ tableKey: 'orders', columns: COLUMNS });
    const [viewing, setViewing] = useState<OrderRow | null>(null);

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
        <AdminLayout title={t('admin.orders.title')}>
            <Head title={t('admin.orders.title')} />

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
                        {t(`status.${s}`)}
                        {counts[s] ? ` (${counts[s]})` : ''}
                    </button>
                ))}
            </div>

            <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm text-neutral-400">{t('admin.orders.count', { n: orders.total })}</span>
                    {rc.isDefault ? (
                        <span className="hidden items-center gap-1.5 text-xs text-neutral-500 lg:inline-flex">
                            <MoveHorizontal className="h-3.5 w-3.5" /> {t('admin.common.dragToResize')}
                        </span>
                    ) : (
                        <Button size="sm" variant="ghost" icon={Columns3} onClick={rc.resetAll}>{t('admin.common.resetColumns')}</Button>
                    )}
                </div>
                <ExportButtons base="/admin/orders/export" params={exportParams} />
            </div>

            <StickyScrollWrapper className="rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="min-w-full table-fixed text-sm" style={{ width: rc.tableWidth }}>
                    <thead className="border-b border-neutral-200 bg-neutral-50 text-left text-neutral-600 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-300">
                        <tr>
                            <ResizableTh colKey="order" width={rc.widths.order} resizeProps={rc.getResizeHandleProps('order')} resizing={rc.resizing === 'order'} sortKey="order_number" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.orders.cols.order')}</ResizableTh>
                            <ResizableTh colKey="customer" width={rc.widths.customer} resizeProps={rc.getResizeHandleProps('customer')} resizing={rc.resizing === 'customer'} sortKey="customer_name" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.orders.cols.customer')}</ResizableTh>
                            <ResizableTh colKey="status" width={rc.widths.status} resizeProps={rc.getResizeHandleProps('status')} resizing={rc.resizing === 'status'} sortKey="status" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.orders.cols.status')}</ResizableTh>
                            <ResizableTh colKey="payment" width={rc.widths.payment} resizeProps={rc.getResizeHandleProps('payment')} resizing={rc.resizing === 'payment'} sortKey="payment_status" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.orders.cols.payment')}</ResizableTh>
                            <ResizableTh colKey="total" width={rc.widths.total} resizeProps={rc.getResizeHandleProps('total')} resizing={rc.resizing === 'total'} sortKey="total" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.orders.cols.total')}</ResizableTh>
                            <ResizableTh colKey="placed" width={rc.widths.placed} resizeProps={rc.getResizeHandleProps('placed')} resizing={rc.resizing === 'placed'} sortKey="created_at" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.orders.cols.placed')}</ResizableTh>
                            <ResizableTh colKey="actions" width={rc.widths.actions} resizeProps={rc.getResizeHandleProps('actions')} resizing={rc.resizing === 'actions'} className="text-end">{t('admin.common.actions')}</ResizableTh>
                        </tr>
                    </thead>
                    <tbody>
                        {orders.data.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-8 text-center text-neutral-400">
                                    {t('admin.orders.empty')}
                                </td>
                            </tr>
                        )}
                        {orders.data.map((order) => (
                            <tr
                                key={order.order_number}
                                className="border-b border-neutral-100 last:border-0 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/50"
                            >
                                <td className="truncate px-4 py-3 font-mono font-medium text-neutral-800 dark:text-neutral-100">{order.order_number}</td>
                                <td className="truncate px-4 py-3" dir="auto">{order.customer_name ?? '—'}</td>
                                <td className="px-4 py-3"><OrderStatusBadge status={order.status} /></td>
                                <td className="px-4 py-3">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <span className="truncate text-neutral-500">
                                            {order.payment_method ? t(`admin.paymentMethod.${order.payment_method}`) : '—'}
                                        </span>
                                        <PaymentStatusBadge status={order.payment_status} />
                                    </div>
                                </td>
                                <td className="px-4 py-3">{order.total} {t('admin.common.sar')}</td>
                                <td className="truncate px-4 py-3 text-neutral-500">{order.created_at ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end">
                                        <Button size="sm" variant="secondary" icon={Eye} onClick={() => setViewing(order)}>{t('admin.common.view')}</Button>
                                    </div>
                                </td>
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

            <Modal
                open={viewing !== null}
                onClose={() => setViewing(null)}
                size="lg"
                title={<span className="font-mono">{viewing?.order_number}</span>}
            >
                {viewing && <OrderDetail key={viewing.order_number} orderNumber={viewing.order_number} />}
            </Modal>
        </AdminLayout>
    );
}
