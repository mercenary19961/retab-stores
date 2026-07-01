import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import OrderStatusBadge from '@/components/order-status-badge';

interface Item {
    name: string;
    sku: string | null;
    unit_price: number;
    quantity: number;
    line_total: number;
}

interface Activity {
    type: string;
    from_status: string | null;
    to_status: string | null;
    note: string | null;
    user: string | null;
    created_at: string | null;
}

interface Order {
    order_number: string;
    customer_name: string | null;
    customer_email: string | null;
    customer_phone: string | null;
    shipping_address: Record<string, string | null> | null;
    status: string;
    payment_status: string;
    payment_method: string | null;
    subtotal: number;
    discount_total: number;
    shipping_fee: number;
    total: number;
    currency: string;
    tracking_number: string | null;
    carrier: string | null;
    admin_notes: string | null;
    confirmed_by: string | null;
    confirmed_at: string | null;
    delivered_at: string | null;
    created_at: string | null;
    items: Item[];
    activities: Activity[];
}

interface Can {
    confirm: boolean;
    unavailable: boolean;
    ship: boolean;
    cancel: boolean;
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex justify-between py-1 text-sm">
            <span className="text-neutral-500">{label}</span>
            <span>{value}</span>
        </div>
    );
}

export default function OrderShow({ order, can }: { order: Order; can: Can }) {
    const [busy, setBusy] = useState(false);
    const [note, setNote] = useState('');

    const action = (verb: string, data: Record<string, string> = {}, confirmMsg?: string) => {
        if (confirmMsg && !window.confirm(confirmMsg)) return;
        router.post(`/admin/orders/${order.order_number}/${verb}`, data, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    };

    const addr = order.shipping_address ?? {};

    return (
        <AdminLayout>
            <Head title={`Order ${order.order_number}`} />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <Link href="/admin/orders" className="text-sm text-neutral-500 hover:underline">
                        ← Orders
                    </Link>
                    <h1 className="mt-1 flex items-center gap-3 text-2xl font-bold">
                        <span className="font-mono">{order.order_number}</span>
                        <OrderStatusBadge status={order.status} />
                    </h1>
                </div>
            </div>

            {/* Actions */}
            {(can.confirm || can.unavailable || can.ship || can.cancel) && (
                <div className="mb-6 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="flex flex-wrap items-center gap-3">
                        {can.confirm && (
                            <button
                                type="button"
                                disabled={busy}
                                onClick={() => action('confirm', {}, 'Confirm this order? Stock will be deducted.')}
                                className="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60"
                            >
                                Confirm order
                            </button>
                        )}
                        {can.ship && (
                            <button
                                type="button"
                                disabled={busy}
                                onClick={() => action('ship', {}, 'Create the OTO shipment and request pickup?')}
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                            >
                                Ship (OTO pickup)
                            </button>
                        )}
                        {can.cancel && (
                            <button
                                type="button"
                                disabled={busy}
                                onClick={() => action('cancel', {}, 'Cancel this order?')}
                                className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 disabled:opacity-60 dark:border-red-900 dark:hover:bg-red-950"
                            >
                                Cancel
                            </button>
                        )}
                    </div>

                    {can.unavailable && (
                        <div className="mt-3 flex flex-wrap items-end gap-3 border-t border-neutral-100 pt-3 dark:border-neutral-800">
                            <label className="flex-1">
                                <span className="text-xs text-neutral-500">Note (optional — reason / customer message)</span>
                                <input
                                    value={note}
                                    onChange={(e) => setNote(e.target.value)}
                                    className="mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                />
                            </label>
                            <button
                                type="button"
                                disabled={busy}
                                onClick={() => action('unavailable', { note }, 'Mark unavailable? This releases the payment hold / flags a refund.')}
                                className="rounded-lg border border-amber-400 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50 disabled:opacity-60 dark:text-amber-300 dark:hover:bg-amber-950"
                            >
                                Mark unavailable
                            </button>
                        </div>
                    )}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {/* Items */}
                    <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-3 font-bold">Items</h2>
                        <table className="w-full text-sm">
                            <thead className="text-left text-neutral-500">
                                <tr>
                                    <th className="py-1 font-medium">Product</th>
                                    <th className="py-1 font-medium">SKU</th>
                                    <th className="py-1 text-right font-medium">Price</th>
                                    <th className="py-1 text-right font-medium">Qty</th>
                                    <th className="py-1 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {order.items.map((item, i) => (
                                    <tr key={i} className="border-t border-neutral-100 dark:border-neutral-800">
                                        <td className="py-2">{item.name}</td>
                                        <td className="py-2 font-mono text-neutral-500">{item.sku ?? '—'}</td>
                                        <td className="py-2 text-right">{item.unit_price}</td>
                                        <td className="py-2 text-right">{item.quantity}</td>
                                        <td className="py-2 text-right">{item.line_total}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="mt-3 border-t border-neutral-200 pt-3 dark:border-neutral-800">
                            <Row label="Subtotal" value={`${order.subtotal} ${order.currency}`} />
                            {order.discount_total > 0 && <Row label="Discount" value={`−${order.discount_total} ${order.currency}`} />}
                            <Row label="Shipping" value={`${order.shipping_fee} ${order.currency}`} />
                            <div className="flex justify-between pt-1 font-bold">
                                <span>Total</span>
                                <span>{order.total} {order.currency}</span>
                            </div>
                        </div>
                    </section>

                    {/* Activity timeline */}
                    <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-3 font-bold">Activity</h2>
                        {order.activities.length === 0 ? (
                            <p className="text-sm text-neutral-400">No activity yet.</p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {order.activities.map((a, i) => (
                                    <li key={i} className="flex justify-between border-b border-neutral-100 pb-2 last:border-0 dark:border-neutral-800">
                                        <span>
                                            {a.type === 'status_change' ? (
                                                <>
                                                    {a.from_status ?? '—'} → <b>{a.to_status}</b>
                                                </>
                                            ) : (
                                                a.type
                                            )}
                                            {a.note && <span className="text-neutral-500"> — {a.note}</span>}
                                            {a.user && <span className="text-neutral-400"> by {a.user}</span>}
                                        </span>
                                        <span className="text-neutral-400">{a.created_at}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-2 font-bold">Customer</h2>
                        <Row label="Name" value={order.customer_name ?? '—'} />
                        <Row label="Phone" value={order.customer_phone ?? '—'} />
                        <Row label="Email" value={order.customer_email ?? '—'} />
                    </section>

                    <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-2 font-bold">Shipping</h2>
                        <Row label="Country" value={addr.country ?? '—'} />
                        <Row label="City" value={addr.city ?? '—'} />
                        {addr.district && <Row label="District" value={addr.district} />}
                        {addr.street && <Row label="Street" value={addr.street} />}
                        {addr.building && <Row label="Building" value={addr.building} />}
                        <Row label="Carrier" value={order.carrier ?? '—'} />
                        <Row label="Tracking" value={order.tracking_number ?? '—'} />
                    </section>

                    <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-2 font-bold">Payment</h2>
                        <Row label="Method" value={order.payment_method ?? '—'} />
                        <Row label="Status" value={order.payment_status} />
                    </section>

                    {(order.confirmed_by || order.confirmed_at || order.delivered_at || order.admin_notes) && (
                        <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                            <h2 className="mb-2 font-bold">Admin</h2>
                            {order.confirmed_by && <Row label="Confirmed by" value={order.confirmed_by} />}
                            {order.confirmed_at && <Row label="Confirmed at" value={order.confirmed_at} />}
                            {order.delivered_at && <Row label="Delivered at" value={order.delivered_at} />}
                            {order.admin_notes && <p className="mt-2 text-sm text-neutral-500">{order.admin_notes}</p>}
                        </section>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
