import { Head, Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { Columns3, MoveHorizontal } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import ExportButtons from '@/components/admin/export-buttons';
import ResizableTh from '@/components/admin/resizable-th';
import StickyScrollWrapper from '@/components/admin/sticky-scroll-wrapper';
import { useResizableColumns, type ColumnDef } from '@/hooks/use-resizable-columns';

const COLUMNS: ColumnDef[] = [
    { key: 'customer', defaultWidth: 200, minWidth: 120 },
    { key: 'phone', defaultWidth: 150, minWidth: 100 },
    { key: 'email', defaultWidth: 220, minWidth: 120 },
    { key: 'opt_in', defaultWidth: 150, minWidth: 100 },
    { key: 'confirmed', defaultWidth: 150, minWidth: 100 },
    { key: 'joined', defaultWidth: 160, minWidth: 110 },
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

export default function CustomersIndex({
    customers,
    filters,
}: {
    customers: Paginator<CustomerRow>;
    filters: Filters;
}) {
    const [search, setSearch] = useState(filters.q ?? '');
    const rc = useResizableColumns({ tableKey: 'customers', columns: COLUMNS });

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
        <AdminLayout title="Customers">
            <Head title="Customers" />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <form onSubmit={submit} className="flex w-full gap-2 sm:w-auto">
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search name / email / phone…"
                        className="min-w-0 flex-1 rounded border border-neutral-300 px-3 py-1.5 text-sm sm:w-64 sm:flex-none dark:border-neutral-700 dark:bg-neutral-950"
                    />
                    <button type="submit" className="shrink-0 rounded bg-neutral-900 px-3 py-1.5 text-sm font-semibold text-white dark:bg-white dark:text-neutral-900">
                        Search
                    </button>
                </form>

                <div className="flex flex-wrap gap-2">
                    {[
                        { label: 'All', value: undefined },
                        { label: 'Opted in', value: '1' },
                        { label: 'Not opted in', value: '0' },
                    ].map((f) => (
                        <button
                            key={f.label}
                            type="button"
                            onClick={() => apply({ opt_in: f.value })}
                            className={`rounded-full px-3 py-1 text-sm ${(filters.opt_in ?? undefined) === f.value ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300'}`}
                        >
                            {f.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm text-neutral-400">{customers.total} customers</span>
                    {rc.isDefault ? (
                        <span className="hidden items-center gap-1.5 text-xs text-neutral-500 lg:inline-flex">
                            <MoveHorizontal className="h-3.5 w-3.5" /> Drag column edges to resize
                        </span>
                    ) : (
                        <Button size="sm" variant="ghost" icon={Columns3} onClick={rc.resetAll}>Reset columns</Button>
                    )}
                </div>
                <ExportButtons base="/admin/customers/export" params={exportParams} />
            </div>

            <StickyScrollWrapper className="rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="min-w-full table-fixed text-sm" style={{ width: rc.tableWidth }}>
                    <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <ResizableTh colKey="customer" width={rc.widths.customer} resizeProps={rc.getResizeHandleProps('customer')} resizing={rc.resizing === 'customer'} sortKey="name" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Customer</ResizableTh>
                            <ResizableTh colKey="phone" width={rc.widths.phone} resizeProps={rc.getResizeHandleProps('phone')} resizing={rc.resizing === 'phone'} sortKey="phone" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Phone</ResizableTh>
                            <ResizableTh colKey="email" width={rc.widths.email} resizeProps={rc.getResizeHandleProps('email')} resizing={rc.resizing === 'email'} sortKey="email" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Email</ResizableTh>
                            <ResizableTh colKey="opt_in" width={rc.widths.opt_in} resizeProps={rc.getResizeHandleProps('opt_in')} resizing={rc.resizing === 'opt_in'} sortKey="whatsapp_opt_in" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>WhatsApp opt-in</ResizableTh>
                            <ResizableTh colKey="confirmed" width={rc.widths.confirmed} resizeProps={rc.getResizeHandleProps('confirmed')} resizing={rc.resizing === 'confirmed'} sortKey="confirmed_purchases_count" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Confirmed orders</ResizableTh>
                            <ResizableTh colKey="joined" width={rc.widths.joined} resizeProps={rc.getResizeHandleProps('joined')} resizing={rc.resizing === 'joined'} sortKey="created_at" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Joined</ResizableTh>
                        </tr>
                    </thead>
                    <tbody>
                        {customers.data.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-8 text-center text-neutral-400">No customers.</td></tr>
                        )}
                        {customers.data.map((c) => (
                            <tr key={c.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td className="px-4 py-3">
                                    <Link href={`/admin/customers/${c.id}`} className="block truncate text-blue-600 underline dark:text-blue-400" dir="auto">
                                        {c.name ?? `#${c.id}`}
                                    </Link>
                                </td>
                                <td className="truncate px-4 py-3 font-mono">{c.phone ?? '—'}</td>
                                <td className="truncate px-4 py-3">{c.email ?? '—'}</td>
                                <td className="px-4 py-3">{c.whatsapp_opt_in ? 'Yes' : 'No'}</td>
                                <td className="px-4 py-3">{c.confirmed_purchases}</td>
                                <td className="truncate px-4 py-3 text-neutral-500">{c.created_at ?? '—'}</td>
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
        </AdminLayout>
    );
}
