import { Head, Link, router } from '@inertiajs/react';
import { Check, RefreshCw, Undo2, X } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';

const STATUS_LABELS: Record<string, string> = {
    requested: 'Requested',
    approved: 'Approved',
    rejected: 'Rejected',
    exchanged: 'Exchanged',
    refunded: 'Refunded',
};

interface ReturnDetail {
    id: number;
    status: string;
    reason: string;
    photos: string[];
    resolution: string | null;
    refund_amount: number | null;
    refund_shipping: boolean;
    admin_notes: string | null;
    resolved_at: string | null;
    resolved_by: string | null;
    created_at: string | null;
    items: { name_ar: string | null; quantity: number; unit_price: number }[];
}

interface OrderSummary {
    order_number: string | null;
    customer_name: string | null;
    customer_phone: string | null;
    payment_method: string | null;
    total: number;
    shipping_fee: number;
    delivered_at: string | null;
}

export default function ReturnShow({
    orderReturn,
    order,
    refundPreview,
}: {
    orderReturn: ReturnDetail;
    order: OrderSummary;
    refundPreview: { items_only: number; with_shipping: number };
}) {
    const [notes, setNotes] = useState('');
    const [refundShipping, setRefundShipping] = useState(false);

    const act = (action: string, extra: Record<string, unknown> = {}) => {
        router.post(`/admin/returns/${orderReturn.id}/${action}`, { notes, ...extra }, { preserveScroll: true });
    };

    return (
        <AdminLayout title={`Return #${orderReturn.id}`}>
            <Head title={`Return #${orderReturn.id}`} />

            <div className="mb-4">
                <Link href="/admin/returns" className="text-sm text-neutral-500 underline">← Returns</Link>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="font-bold">Return #{orderReturn.id}</h2>
                            <span className="rounded-full bg-neutral-100 px-3 py-1 text-sm dark:bg-neutral-800">
                                {STATUS_LABELS[orderReturn.status] ?? orderReturn.status}
                            </span>
                        </div>

                        <p className="mb-1 text-xs font-medium uppercase text-neutral-400">Customer's description</p>
                        <p className="mb-4 whitespace-pre-wrap text-sm" dir="auto">{orderReturn.reason}</p>

                        <p className="mb-1 text-xs font-medium uppercase text-neutral-400">Items</p>
                        <ul className="mb-4 space-y-1 text-sm">
                            {orderReturn.items.map((item, i) => (
                                <li key={i} className="flex justify-between">
                                    <span dir="auto">{item.name_ar ?? '—'} × {item.quantity}</span>
                                    <span className="text-neutral-500">{(item.unit_price * item.quantity).toFixed(2)} SAR</span>
                                </li>
                            ))}
                        </ul>

                        <p className="mb-2 text-xs font-medium uppercase text-neutral-400">Photos</p>
                        {orderReturn.photos.length === 0 ? (
                            <p className="text-sm text-neutral-400">No photos.</p>
                        ) : (
                            <div className="grid grid-cols-3 gap-2 sm:grid-cols-5">
                                {orderReturn.photos.map((url) => (
                                    <a key={url} href={url} target="_blank" rel="noreferrer">
                                        <img src={url} alt="" className="aspect-square w-full rounded object-cover" />
                                    </a>
                                ))}
                            </div>
                        )}
                    </section>

                    {(orderReturn.status === 'requested' || orderReturn.status === 'approved') && (
                        <section className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                            <h2 className="mb-3 font-bold">Resolve</h2>

                            <label className="mb-3 block">
                                <span className="text-sm text-neutral-500">Notes (internal)</span>
                                <textarea
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    rows={2}
                                    className="mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-950"
                                />
                            </label>

                            {orderReturn.status === 'requested' && (
                                <div className="flex gap-3">
                                    <Button variant="success" icon={Check} onClick={() => act('approve')}>Approve</Button>
                                    <Button variant="danger" icon={X} onClick={() => act('reject')}>Reject</Button>
                                </div>
                            )}

                            {orderReturn.status === 'approved' && (
                                <div className="space-y-3">
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={refundShipping}
                                            onChange={(e) => setRefundShipping(e.target.checked)}
                                        />
                                        Refund shipping fee too (only when the goods arrived damaged)
                                    </label>
                                    <p className="text-sm text-neutral-500">
                                        Refund amount: <b>{(refundShipping ? refundPreview.with_shipping : refundPreview.items_only).toFixed(2)} SAR</b>
                                        {' '}via <b>{order.payment_method ?? '—'}</b>
                                        {order.payment_method === 'bank_transfer' && ' (manual transfer — no gateway call)'}
                                    </p>
                                    <div className="flex gap-3">
                                        <Button variant="primary" icon={Undo2} onClick={() => act('refund', { refund_shipping: refundShipping })}>Refund</Button>
                                        <Button variant="secondary" icon={RefreshCw} onClick={() => act('exchange')}>Resolve as exchange</Button>
                                    </div>
                                </div>
                            )}
                        </section>
                    )}
                </div>

                <div className="h-fit space-y-4 rounded-lg border border-neutral-200 bg-white p-5 text-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="font-bold">Order</h2>
                    <dl className="space-y-2">
                        <div className="flex justify-between"><dt className="text-neutral-500">Order</dt><dd className="font-mono">{order.order_number ?? '—'}</dd></div>
                        <div className="flex justify-between"><dt className="text-neutral-500">Customer</dt><dd dir="auto">{order.customer_name ?? '—'}</dd></div>
                        <div className="flex justify-between"><dt className="text-neutral-500">Phone</dt><dd className="font-mono">{order.customer_phone ?? '—'}</dd></div>
                        <div className="flex justify-between"><dt className="text-neutral-500">Payment</dt><dd>{order.payment_method ?? '—'}</dd></div>
                        <div className="flex justify-between"><dt className="text-neutral-500">Total</dt><dd>{order.total.toFixed(2)} SAR</dd></div>
                        <div className="flex justify-between"><dt className="text-neutral-500">Shipping</dt><dd>{order.shipping_fee.toFixed(2)} SAR</dd></div>
                        <div className="flex justify-between"><dt className="text-neutral-500">Delivered</dt><dd>{order.delivered_at ?? '—'}</dd></div>
                        <div className="flex justify-between"><dt className="text-neutral-500">Filed</dt><dd>{orderReturn.created_at ?? '—'}</dd></div>
                    </dl>

                    {orderReturn.resolved_at && (
                        <div className="border-t border-neutral-100 pt-3 dark:border-neutral-800">
                            <p className="text-neutral-500">
                                Resolved {orderReturn.resolution ? `(${orderReturn.resolution})` : ''} at {orderReturn.resolved_at}
                                {orderReturn.resolved_by ? ` by ${orderReturn.resolved_by}` : ''}
                            </p>
                            {orderReturn.refund_amount !== null && (
                                <p className="mt-1">
                                    Refunded <b>{orderReturn.refund_amount.toFixed(2)} SAR</b>
                                    {orderReturn.refund_shipping ? ' (incl. shipping)' : ''}
                                </p>
                            )}
                        </div>
                    )}

                    {orderReturn.admin_notes && (
                        <div className="border-t border-neutral-100 pt-3 dark:border-neutral-800">
                            <p className="mb-1 text-xs font-medium uppercase text-neutral-400">Notes</p>
                            <p className="whitespace-pre-wrap">{orderReturn.admin_notes}</p>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
