import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    Check,
    CreditCard,
    Gavel,
    Hash,
    PackageCheck,
    Phone,
    Receipt,
    RefreshCw,
    RotateCcw,
    ShoppingBag,
    Truck,
    Undo2,
    User,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import { useAdminT } from '@/i18n/use-admin-t';

function DlRow({ icon: Icon, label, value, mono, dir }: { icon: LucideIcon; label: string; value: React.ReactNode; mono?: boolean; dir?: 'auto' }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="flex items-center gap-2 text-neutral-500">
                <Icon className="h-3.5 w-3.5 shrink-0" /> {label}
            </dt>
            <dd className={mono ? 'font-mono' : ''} dir={dir}>{value}</dd>
        </div>
    );
}

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
    const { t } = useAdminT();
    const [notes, setNotes] = useState('');
    const [refundShipping, setRefundShipping] = useState(false);

    const act = (action: string, extra: Record<string, unknown> = {}) => {
        router.post(`/admin/returns/${orderReturn.id}/${action}`, { notes, ...extra }, { preserveScroll: true });
    };

    return (
        <AdminLayout title={t('admin.returns.show.headTitle', { id: orderReturn.id })}>
            <Head title={t('admin.returns.show.headTitle', { id: orderReturn.id })} />

            <div className="mb-4">
                <Link href="/admin/returns" className="inline-flex items-center gap-1 text-sm text-neutral-500 underline">
                    <ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {t('admin.nav.returns')}
                </Link>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="flex items-center gap-2 font-bold">
                                <RotateCcw className="h-4 w-4 text-brand-gold" /> {t('admin.returns.show.headTitle', { id: orderReturn.id })}
                            </h2>
                            <span className="rounded-full bg-neutral-100 px-3 py-1 text-sm dark:bg-neutral-800">
                                {t(`admin.returns.status.${orderReturn.status}`)}
                            </span>
                        </div>

                        <p className="mb-1 text-xs font-medium uppercase text-neutral-400">{t('admin.returns.show.customerDescription')}</p>
                        <p className="mb-4 whitespace-pre-wrap text-sm" dir="auto">{orderReturn.reason}</p>

                        <p className="mb-1 text-xs font-medium uppercase text-neutral-400">{t('admin.returns.show.items')}</p>
                        <ul className="mb-4 space-y-1 text-sm">
                            {orderReturn.items.map((item, i) => (
                                <li key={i} className="flex justify-between">
                                    <span dir="auto">{item.name_ar ?? '—'} × {item.quantity}</span>
                                    <span className="text-neutral-500">{(item.unit_price * item.quantity).toFixed(2)} {t('admin.common.sar')}</span>
                                </li>
                            ))}
                        </ul>

                        <p className="mb-2 text-xs font-medium uppercase text-neutral-400">{t('admin.returns.show.photos')}</p>
                        {orderReturn.photos.length === 0 ? (
                            <p className="text-sm text-neutral-400">{t('admin.returns.show.noPhotos')}</p>
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
                            <h2 className="mb-3 flex items-center gap-2 font-bold">
                                <Gavel className="h-4 w-4 text-brand-gold" /> {t('admin.returns.show.resolve')}
                            </h2>

                            <label className="mb-3 block">
                                <span className="text-sm text-neutral-500">{t('admin.returns.show.notesInternal')}</span>
                                <textarea
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    rows={2}
                                    className="mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-950"
                                />
                            </label>

                            {orderReturn.status === 'requested' && (
                                <div className="flex gap-3">
                                    <Button variant="success" icon={Check} onClick={() => act('approve')}>{t('admin.returns.show.approve')}</Button>
                                    <Button variant="danger" icon={X} onClick={() => act('reject')}>{t('admin.returns.show.reject')}</Button>
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
                                        {t('admin.returns.show.refundShippingLabel')}
                                    </label>
                                    <p className="text-sm text-neutral-500">
                                        {t('admin.returns.show.refundAmountLabel')} <b>{(refundShipping ? refundPreview.with_shipping : refundPreview.items_only).toFixed(2)} {t('admin.common.sar')}</b>
                                        {' '}{t('admin.returns.show.via')} <b>{order.payment_method ?? '—'}</b>
                                        {order.payment_method === 'bank_transfer' && ` ${t('admin.returns.show.manualNote')}`}
                                    </p>
                                    <div className="flex gap-3">
                                        <Button variant="primary" icon={Undo2} onClick={() => act('refund', { refund_shipping: refundShipping })}>{t('admin.returns.show.refund')}</Button>
                                        <Button variant="secondary" icon={RefreshCw} onClick={() => act('exchange')}>{t('admin.returns.show.resolveAsExchange')}</Button>
                                    </div>
                                </div>
                            )}
                        </section>
                    )}
                </div>

                <div className="h-fit space-y-4 rounded-lg border border-neutral-200 bg-white p-5 text-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="flex items-center gap-2 font-bold">
                        <ShoppingBag className="h-4 w-4 text-brand-gold" /> {t('admin.common.order')}
                    </h2>
                    <dl className="space-y-2">
                        <DlRow icon={Hash} label={t('admin.common.order')} value={order.order_number ?? '—'} mono />
                        <DlRow icon={User} label={t('admin.common.customer')} value={order.customer_name ?? '—'} dir="auto" />
                        <DlRow icon={Phone} label={t('admin.common.phone')} value={order.customer_phone ?? '—'} mono />
                        <DlRow icon={CreditCard} label={t('admin.common.payment')} value={order.payment_method ?? '—'} />
                        <DlRow icon={Receipt} label={t('admin.common.total')} value={`${order.total.toFixed(2)} ${t('admin.common.sar')}`} />
                        <DlRow icon={Truck} label={t('admin.common.shipping')} value={`${order.shipping_fee.toFixed(2)} ${t('admin.common.sar')}`} />
                        <DlRow icon={PackageCheck} label={t('admin.returns.show.delivered')} value={order.delivered_at ?? '—'} />
                        <DlRow icon={Calendar} label={t('admin.returns.show.filed')} value={orderReturn.created_at ?? '—'} />
                    </dl>

                    {orderReturn.resolved_at && (
                        <div className="border-t border-neutral-100 pt-3 dark:border-neutral-800">
                            <p className="text-neutral-500">
                                {t('admin.returns.show.resolved')} {orderReturn.resolution ? `(${t(`admin.returns.status.${orderReturn.resolution}`)})` : ''} {orderReturn.resolved_at}
                                {orderReturn.resolved_by ? ` ${t('admin.common.by', { user: orderReturn.resolved_by })}` : ''}
                            </p>
                            {orderReturn.refund_amount !== null && (
                                <p className="mt-1">
                                    {t('admin.returns.show.refundedLine')} <b>{orderReturn.refund_amount.toFixed(2)} {t('admin.common.sar')}</b>
                                    {orderReturn.refund_shipping ? ` ${t('admin.returns.show.inclShipping')}` : ''}
                                </p>
                            )}
                        </div>
                    )}

                    {orderReturn.admin_notes && (
                        <div className="border-t border-neutral-100 pt-3 dark:border-neutral-800">
                            <p className="mb-1 text-xs font-medium uppercase text-neutral-400">{t('admin.common.notes')}</p>
                            <p className="whitespace-pre-wrap">{orderReturn.admin_notes}</p>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
