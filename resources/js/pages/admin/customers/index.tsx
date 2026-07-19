import { Head, Link, router } from '@inertiajs/react';
import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import { Calendar, Columns3, Eye, Gift, Languages, Mail, MapPin, MessageCircle, MoveHorizontal, Phone, ShoppingBag, User, type LucideIcon } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import ExportButtons from '@/components/admin/export-buttons';
import Modal from '@/components/admin/modal';
import PaymentStatusBadge from '@/components/admin/payment-status-badge';
import ResizableTh from '@/components/admin/resizable-th';
import StickyScrollWrapper from '@/components/admin/sticky-scroll-wrapper';
import { useResizableColumns, type ColumnDef } from '@/hooks/use-resizable-columns';
import { useAdminT } from '@/i18n/use-admin-t';

const COLUMNS: ColumnDef[] = [
    { key: 'customer', defaultWidth: 200, minWidth: 120 },
    { key: 'phone', defaultWidth: 150, minWidth: 100 },
    { key: 'email', defaultWidth: 220, minWidth: 120 },
    { key: 'opt_in', defaultWidth: 150, minWidth: 100 },
    { key: 'confirmed', defaultWidth: 150, minWidth: 100 },
    { key: 'joined', defaultWidth: 160, minWidth: 110 },
    { key: 'actions', defaultWidth: 120, minWidth: 90 },
];

interface CustomerRow {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    whatsapp_opt_in: boolean;
    confirmed_purchases: number;
    created_at: string | null;
}

interface Filters {
    q: string;
    opt_in: string | null;
    sort: string | null;
    direction: 'asc' | 'desc';
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface CustomerDetailData {
    customer: {
        id: number;
        name: string | null;
        email: string | null;
        phone: string | null;
        city: string | null;
        locale: string | null;
        phone_verified: boolean;
        whatsapp_opt_in: boolean;
        whatsapp_opt_in_at: string | null;
        created_at: string | null;
    };
    loyalty: { confirmed_purchases: number; milestone: number; progress: number; rewards: { code: string; value: number; is_active: boolean; source: string | null }[] };
    orders: { order_number: string; status: string; payment_status: string; total: number; created_at: string | null }[];
}

function DlRow({ icon: Icon, label, value, mono, dir }: { icon: LucideIcon; label: string; value: ReactNode; mono?: boolean; dir?: 'auto' }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="flex items-center gap-2 text-neutral-500"><Icon className="h-3.5 w-3.5 shrink-0" /> {label}</dt>
            <dd className={mono ? 'font-mono' : ''} dir={dir}>{value}</dd>
        </div>
    );
}

// Modal body: fetches the customer's read-only profile, loyalty and recent orders.
function CustomerDetail({ id }: { id: number }) {
    const { t } = useAdminT();
    const [data, setData] = useState<CustomerDetailData | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let alive = true;
        fetch(`/admin/customers/${id}/detail`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => {
                if (!r.ok) throw new Error();
                return r.json();
            })
            .then((d: CustomerDetailData) => alive && setData(d))
            .catch(() => alive && setFailed(true));
        return () => { alive = false; };
    }, [id]);

    if (failed) return <p className="py-6 text-sm text-red-500">{t('admin.customers.detailLoadError')}</p>;
    if (!data) return <p className="py-8 text-center text-sm text-neutral-400">{t('admin.common.loading')}</p>;

    const { customer, loyalty, orders } = data;

    return (
        <div className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
                <section className="rounded-lg border border-neutral-200 p-4 text-sm dark:border-neutral-800">
                    <h3 className="mb-3 flex items-center gap-2 font-semibold" dir="auto">
                        <User className="h-4 w-4 shrink-0 text-brand-gold" /> {customer.name ?? '—'}
                    </h3>
                    <dl className="space-y-2">
                        <DlRow icon={Phone} label={t('admin.common.phone')} value={`${customer.phone ?? '—'}${customer.phone_verified ? ' ✓' : ''}`} mono />
                        <DlRow icon={Mail} label={t('admin.common.email')} value={customer.email ?? '—'} />
                        <DlRow icon={MapPin} label={t('admin.common.city')} value={customer.city ?? '—'} dir="auto" />
                        <DlRow icon={Languages} label={t('admin.customers.show.locale')} value={customer.locale ?? '—'} />
                        <DlRow icon={Calendar} label={t('admin.customers.show.joined')} value={customer.created_at ?? '—'} />
                        <DlRow
                            icon={MessageCircle}
                            label={t('admin.customers.show.optIn')}
                            value={customer.whatsapp_opt_in ? `${t('admin.common.yes')}${customer.whatsapp_opt_in_at ? ` (${customer.whatsapp_opt_in_at})` : ''}` : t('admin.common.no')}
                        />
                    </dl>
                </section>

                <section className="rounded-lg border border-neutral-200 p-4 text-sm dark:border-neutral-800">
                    <h3 className="mb-1 flex items-center gap-2 font-semibold"><Gift className="h-4 w-4 text-brand-gold" /> {t('admin.customers.show.loyalty')}</h3>
                    <p className="mb-3 text-neutral-500">
                        {t('admin.customers.show.loyaltyProgress', { confirmed: loyalty.confirmed_purchases, progress: loyalty.progress, milestone: loyalty.milestone })}
                    </p>
                    <div className="mb-4 flex gap-1.5">
                        {Array.from({ length: loyalty.milestone }).map((_, i) => (
                            <div key={i} className={`h-2.5 flex-1 rounded-full ${i < loyalty.progress ? 'bg-brand-teal' : 'bg-neutral-200 dark:bg-neutral-700'}`} />
                        ))}
                    </div>
                    {loyalty.rewards.length === 0 ? (
                        <p className="text-neutral-400">{t('admin.customers.show.noRewards')}</p>
                    ) : (
                        <ul className="space-y-1">
                            {loyalty.rewards.map((r) => (
                                <li key={r.code} className="flex justify-between">
                                    <span className="font-mono">{r.code}</span>
                                    <span>{r.value}% {r.is_active ? '' : t('admin.customers.show.rewardUsed')}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <section>
                <h3 className="mb-3 flex items-center gap-2 font-semibold"><ShoppingBag className="h-4 w-4 text-brand-gold" /> {t('admin.customers.show.orders')}</h3>
                {orders.length === 0 ? (
                    <p className="text-sm text-neutral-400">{t('admin.orders.empty')}</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                                <tr>
                                    <th className="py-2 font-medium">{t('admin.common.order')}</th>
                                    <th className="py-2 font-medium">{t('admin.common.status')}</th>
                                    <th className="py-2 font-medium">{t('admin.common.payment')}</th>
                                    <th className="py-2 font-medium">{t('admin.common.total')}</th>
                                    <th className="py-2 font-medium">{t('admin.common.date')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.map((o) => (
                                    <tr key={o.order_number} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                        <td className="py-2">
                                            <Link href={`/admin/orders/${o.order_number}`} className="font-mono text-neutral-700 underline decoration-neutral-300 underline-offset-2 transition-colors hover:text-brand-gold dark:text-neutral-200">
                                                {o.order_number}
                                            </Link>
                                        </td>
                                        <td className="py-2">{t(`status.${o.status}`)}</td>
                                        <td className="py-2"><PaymentStatusBadge status={o.payment_status} /></td>
                                        <td className="whitespace-nowrap py-2">{o.total.toFixed(2)} {t('admin.common.sar')}</td>
                                        <td className="whitespace-nowrap py-2 text-neutral-500">{o.created_at ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </div>
    );
}

export default function CustomersIndex({
    customers,
    filters,
}: {
    customers: Paginator<CustomerRow>;
    filters: Filters;
}) {
    const { t } = useAdminT();
    const [search, setSearch] = useState(filters.q ?? '');
    const rc = useResizableColumns({ tableKey: 'customers', columns: COLUMNS });
    const [viewing, setViewing] = useState<CustomerRow | null>(null);

    const apply = (extra: Record<string, string | undefined> = {}) => {
        const params: Record<string, string> = {};
        if (search) params.q = search;
        if (filters.opt_in) params.opt_in = filters.opt_in;
        if (filters.sort) {
            params.sort = filters.sort;
            params.direction = filters.direction;
        }
        Object.entries(extra).forEach(([k, v]) => {
            if (v === undefined) delete params[k];
            else params[k] = v;
        });
        router.get('/admin/customers', params, { preserveState: true, preserveScroll: true });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        apply();
    };

    const toggleSort = (col: string) => {
        const direction = filters.sort === col && filters.direction === 'asc' ? 'desc' : 'asc';
        apply({ sort: col, direction });
    };

    const exportParams = {
        q: filters.q,
        opt_in: filters.opt_in,
        sort: filters.sort,
        direction: filters.sort ? filters.direction : undefined,
    };

    return (
        <AdminLayout title={t('admin.customers.title')}>
            <Head title={t('admin.customers.title')} />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <form onSubmit={submit} className="flex w-full gap-2 sm:w-auto">
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('admin.customers.searchPlaceholder')}
                        className="min-w-0 flex-1 rounded border border-neutral-300 px-3 py-1.5 text-sm sm:w-64 sm:flex-none dark:border-neutral-700 dark:bg-neutral-950"
                    />
                    <button type="submit" className="shrink-0 rounded bg-neutral-900 px-3 py-1.5 text-sm font-semibold text-white dark:bg-white dark:text-neutral-900">
                        {t('admin.common.search')}
                    </button>
                </form>

                <div className="flex flex-wrap gap-2">
                    {[
                        { key: 'all', value: undefined },
                        { key: 'optedIn', value: '1' },
                        { key: 'notOptedIn', value: '0' },
                    ].map((f) => (
                        <button
                            key={f.key}
                            type="button"
                            onClick={() => apply({ opt_in: f.value })}
                            className={`rounded-full px-3 py-1 text-sm ${(filters.opt_in ?? undefined) === f.value ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300'}`}
                        >
                            {t(`admin.customers.filters.${f.key}`)}
                        </button>
                    ))}
                </div>
            </div>

            <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm text-neutral-400">{t('admin.customers.count', { n: customers.total })}</span>
                    {rc.isDefault ? (
                        <span className="hidden items-center gap-1.5 text-xs text-neutral-500 lg:inline-flex">
                            <MoveHorizontal className="h-3.5 w-3.5" /> {t('admin.common.dragToResize')}
                        </span>
                    ) : (
                        <Button size="sm" variant="ghost" icon={Columns3} onClick={rc.resetAll}>{t('admin.common.resetColumns')}</Button>
                    )}
                </div>
                <ExportButtons base="/admin/customers/export" params={exportParams} />
            </div>

            <StickyScrollWrapper className="rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="min-w-full table-fixed text-sm" style={{ width: rc.tableWidth }}>
                    <thead className="border-b border-neutral-200 bg-neutral-50 text-left text-neutral-600 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-300">
                        <tr>
                            <ResizableTh colKey="customer" width={rc.widths.customer} resizeProps={rc.getResizeHandleProps('customer')} resizing={rc.resizing === 'customer'} sortKey="name" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.customers.cols.customer')}</ResizableTh>
                            <ResizableTh colKey="phone" width={rc.widths.phone} resizeProps={rc.getResizeHandleProps('phone')} resizing={rc.resizing === 'phone'} sortKey="phone" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.customers.cols.phone')}</ResizableTh>
                            <ResizableTh colKey="email" width={rc.widths.email} resizeProps={rc.getResizeHandleProps('email')} resizing={rc.resizing === 'email'} sortKey="email" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.customers.cols.email')}</ResizableTh>
                            <ResizableTh colKey="opt_in" width={rc.widths.opt_in} resizeProps={rc.getResizeHandleProps('opt_in')} resizing={rc.resizing === 'opt_in'} sortKey="whatsapp_opt_in" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.customers.cols.optIn')}</ResizableTh>
                            <ResizableTh colKey="confirmed" width={rc.widths.confirmed} resizeProps={rc.getResizeHandleProps('confirmed')} resizing={rc.resizing === 'confirmed'} sortKey="confirmed_purchases_count" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.customers.cols.confirmed')}</ResizableTh>
                            <ResizableTh colKey="joined" width={rc.widths.joined} resizeProps={rc.getResizeHandleProps('joined')} resizing={rc.resizing === 'joined'} sortKey="created_at" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.customers.cols.joined')}</ResizableTh>
                            <ResizableTh colKey="actions" width={rc.widths.actions} resizeProps={rc.getResizeHandleProps('actions')} resizing={rc.resizing === 'actions'} className="text-end">{t('admin.common.actions')}</ResizableTh>
                        </tr>
                    </thead>
                    <tbody>
                        {customers.data.length === 0 && (
                            <tr><td colSpan={7} className="px-4 py-8 text-center text-neutral-400">{t('admin.customers.empty')}</td></tr>
                        )}
                        {customers.data.map((c) => (
                            <tr key={c.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td className="px-4 py-3">
                                    <span dir="auto" className="block truncate font-medium text-neutral-800 dark:text-neutral-100">{c.name ?? `#${c.id}`}</span>
                                </td>
                                <td className="truncate px-4 py-3 font-mono">{c.phone ?? '—'}</td>
                                <td className="truncate px-4 py-3">{c.email ?? '—'}</td>
                                <td className="px-4 py-3">{c.whatsapp_opt_in ? t('admin.common.yes') : t('admin.common.no')}</td>
                                <td className="px-4 py-3">{c.confirmed_purchases}</td>
                                <td className="truncate px-4 py-3 text-neutral-500">{c.created_at ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end">
                                        <Button size="sm" variant="secondary" icon={Eye} onClick={() => setViewing(c)}>{t('admin.common.view')}</Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </StickyScrollWrapper>

            {customers.total > customers.data.length && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {customers.links.map((link, i) => (
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

            <Modal open={viewing !== null} onClose={() => setViewing(null)} size="lg" title={viewing ? (viewing.name ?? `#${viewing.id}`) : ''}>
                {viewing && <CustomerDetail key={viewing.id} id={viewing.id} />}
            </Modal>
        </AdminLayout>
    );
}
