import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { ORDER_STATUS_LABELS } from '@/components/order-status-badge';

interface Customer {
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
}

interface Loyalty {
    confirmed_purchases: number;
    milestone: number;
    progress: number;
    rewards: { code: string; value: number; is_active: boolean; source: string | null }[];
}

interface OrderRow {
    order_number: string;
    status: string;
    payment_status: string;
    total: number;
    created_at: string | null;
}

export default function CustomerShow({
    customer,
    loyalty,
    orders,
}: {
    customer: Customer;
    loyalty: Loyalty;
    orders: OrderRow[];
}) {
    return (
        <AdminLayout title={customer.name ?? `Customer #${customer.id}`}>
            <Head title={customer.name ?? `Customer #${customer.id}`} />

            <div className="mb-4">
                <Link href="/admin/customers" className="text-sm text-neutral-500 underline">← Customers</Link>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-1">
                    <section className="rounded-lg border border-neutral-200 bg-white p-5 text-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-3 font-bold" dir="auto">{customer.name ?? '—'}</h2>
                        <dl className="space-y-2">
                            <div className="flex justify-between"><dt className="text-neutral-500">Phone</dt><dd className="font-mono">{customer.phone ?? '—'}{customer.phone_verified ? ' ✓' : ''}</dd></div>
                            <div className="flex justify-between"><dt className="text-neutral-500">Email</dt><dd>{customer.email ?? '—'}</dd></div>
                            <div className="flex justify-between"><dt className="text-neutral-500">City</dt><dd dir="auto">{customer.city ?? '—'}</dd></div>
                            <div className="flex justify-between"><dt className="text-neutral-500">Locale</dt><dd>{customer.locale ?? '—'}</dd></div>
                            <div className="flex justify-between"><dt className="text-neutral-500">Joined</dt><dd>{customer.created_at ?? '—'}</dd></div>
                            <div className="flex justify-between">
                                <dt className="text-neutral-500">WhatsApp opt-in</dt>
                                <dd>{customer.whatsapp_opt_in ? `Yes${customer.whatsapp_opt_in_at ? ` (${customer.whatsapp_opt_in_at})` : ''}` : 'No'}</dd>
                            </div>
                        </dl>
                    </section>

                    <section className="rounded-lg border border-neutral-200 bg-white p-5 text-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-1 font-bold">Loyalty</h2>
                        <p className="mb-3 text-neutral-500">
                            {loyalty.confirmed_purchases} confirmed orders — {loyalty.progress}/{loyalty.milestone} toward the next reward
                        </p>
                        <div className="mb-4 flex gap-1.5">
                            {Array.from({ length: loyalty.milestone }).map((_, i) => (
                                <div key={i} className={`h-2.5 flex-1 rounded-full ${i < loyalty.progress ? 'bg-neutral-900 dark:bg-white' : 'bg-neutral-200 dark:bg-neutral-700'}`} />
                            ))}
                        </div>
                        {loyalty.rewards.length === 0 ? (
                            <p className="text-neutral-400">No reward coupons issued.</p>
                        ) : (
                            <ul className="space-y-1">
                                {loyalty.rewards.map((r) => (
                                    <li key={r.code} className="flex justify-between">
                                        <span className="font-mono">{r.code}</span>
                                        <span>{r.value}% {r.is_active ? '' : '(used/inactive)'}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

                <section className="rounded-lg border border-neutral-200 bg-white p-5 lg:col-span-2 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="mb-3 font-bold">Orders</h2>
                    {orders.length === 0 ? (
                        <p className="text-sm text-neutral-400">No orders.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                                <tr>
                                    <th className="py-2 font-medium">Order</th>
                                    <th className="py-2 font-medium">Status</th>
                                    <th className="py-2 font-medium">Payment</th>
                                    <th className="py-2 font-medium">Total</th>
                                    <th className="py-2 font-medium">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.map((o) => (
                                    <tr key={o.order_number} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                        <td className="py-2">
                                            <Link href={`/admin/orders/${o.order_number}`} className="font-mono text-blue-600 underline dark:text-blue-400">
                                                {o.order_number}
                                            </Link>
                                        </td>
                                        <td className="py-2">{ORDER_STATUS_LABELS[o.status] ?? o.status}</td>
                                        <td className="py-2">{o.payment_status}</td>
                                        <td className="py-2">{o.total.toFixed(2)} SAR</td>
                                        <td className="py-2 text-neutral-500">{o.created_at ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            </div>
        </AdminLayout>
    );
}
