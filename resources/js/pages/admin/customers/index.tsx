import { Head, Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';

interface CustomerRow {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    whatsapp_opt_in: boolean;
    confirmed_purchases: number;
    created_at: string | null;
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
    filters: { q: string; opt_in: string | null };
}) {
    const [search, setSearch] = useState(filters.q ?? '');

    const apply = (extra: Record<string, string | undefined> = {}) => {
        const params: Record<string, string> = {};
        if (search) params.q = search;
        if (filters.opt_in) params.opt_in = filters.opt_in;
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

    return (
        <AdminLayout title="Customers">
            <Head title="Customers" />

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <form onSubmit={submit} className="flex gap-2">
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search name / email / phone…"
                        className="w-64 rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-950"
                    />
                    <button type="submit" className="rounded bg-neutral-900 px-3 py-1.5 text-sm font-semibold text-white dark:bg-white dark:text-neutral-900">
                        Search
                    </button>
                </form>

                <div className="flex gap-2">
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

                <span className="text-sm text-neutral-400">{customers.total} customers</span>
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-sm">
                    <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Customer</th>
                            <th className="px-4 py-3 font-medium">Phone</th>
                            <th className="px-4 py-3 font-medium">Email</th>
                            <th className="px-4 py-3 font-medium">WhatsApp opt-in</th>
                            <th className="px-4 py-3 font-medium">Confirmed orders</th>
                            <th className="px-4 py-3 font-medium">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        {customers.data.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-8 text-center text-neutral-400">No customers.</td></tr>
                        )}
                        {customers.data.map((c) => (
                            <tr key={c.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td className="px-4 py-3">
                                    <Link href={`/admin/customers/${c.id}`} className="text-blue-600 underline dark:text-blue-400" dir="auto">
                                        {c.name ?? `#${c.id}`}
                                    </Link>
                                </td>
                                <td className="px-4 py-3 font-mono">{c.phone ?? '—'}</td>
                                <td className="px-4 py-3">{c.email ?? '—'}</td>
                                <td className="px-4 py-3">{c.whatsapp_opt_in ? 'Yes' : 'No'}</td>
                                <td className="px-4 py-3">{c.confirmed_purchases}</td>
                                <td className="px-4 py-3 text-neutral-500">{c.created_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
