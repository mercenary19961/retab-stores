import {
    Ban,
    Building2,
    CalendarCheck,
    Check,
    CircleDollarSign,
    CreditCard,
    Globe,
    History,
    Mail,
    MapPin,
    Package,
    PackageCheck,
    PackageSearch,
    Phone,
    ShieldCheck,
    Signpost,
    Truck,
    User,
    UserCheck,
    X,
    type LucideIcon,
} from 'lucide-react';
import { type ReactNode, useState } from 'react';
import Button from '@/components/admin/button';
import OrderStatusBadge from '@/components/order-status-badge';
import PaymentStatusBadge from '@/components/admin/payment-status-badge';
import { useAdminT } from '@/i18n/use-admin-t';

export interface OrderItem {
    name: string;
    sku: string | null;
    unit_price: number;
    quantity: number;
    line_total: number;
}

export interface OrderActivity {
    type: string;
    from_status: string | null;
    to_status: string | null;
    note: string | null;
    user: string | null;
    created_at: string | null;
}

export interface OrderDetailData {
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
    items: OrderItem[];
    activities: OrderActivity[];
}

export interface OrderCan {
    confirm: boolean;
    unavailable: boolean;
    ship: boolean;
    cancel: boolean;
}

function Row({ label, value, icon: Icon }: { label: string; value: ReactNode; icon?: LucideIcon }) {
    return (
        <div className="flex items-center justify-between gap-3 py-1 text-sm">
            <span className="flex items-center gap-2 text-neutral-500">
                {Icon && <Icon className="h-3.5 w-3.5 shrink-0" />}
                {label}
            </span>
            <span className="text-end">{value}</span>
        </div>
    );
}

function SectionHeader({ icon: Icon, children }: { icon: LucideIcon; children: ReactNode }) {
    return (
        <h2 className="mb-3 flex items-center gap-2 font-bold">
            <Icon className="h-4 w-4 text-brand-gold" />
            {children}
        </h2>
    );
}

/**
 * The order detail body (status, lifecycle actions, items, activity, sidebars),
 * shared by the full order page and the in-list modal. `onAction` performs a
 * lifecycle verb (the caller decides how — Inertia post + reload/refetch).
 */
export default function OrderDetailView({
    order,
    can,
    onAction,
    busy,
}: {
    order: OrderDetailData;
    can: OrderCan;
    onAction: (verb: string, data?: Record<string, string>, confirmMsg?: string) => void;
    busy: boolean;
}) {
    const { t } = useAdminT();
    const [note, setNote] = useState('');
    const addr = order.shipping_address ?? {};
    const hasActions = can.confirm || can.unavailable || can.ship || can.cancel;

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center gap-3">
                <OrderStatusBadge status={order.status} />
                <span className="text-sm text-neutral-400">{order.created_at ?? '—'}</span>
            </div>

            {hasActions && (
                <div className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="flex flex-wrap items-center gap-3">
                        {can.confirm && (
                            <Button variant="success" icon={Check} disabled={busy} onClick={() => onAction('confirm', {}, t('admin.orders.show.confirmMsg'))}>
                                {t('admin.orders.show.confirmOrder')}
                            </Button>
                        )}
                        {can.ship && (
                            <Button variant="primary" icon={Truck} disabled={busy} onClick={() => onAction('ship', {}, t('admin.orders.show.shipMsg'))}>
                                {t('admin.orders.show.ship')}
                            </Button>
                        )}
                        {can.cancel && (
                            <Button variant="danger" icon={X} disabled={busy} onClick={() => onAction('cancel', {}, t('admin.orders.show.cancelMsg'))}>
                                {t('admin.orders.show.cancel')}
                            </Button>
                        )}
                    </div>

                    {can.unavailable && (
                        <div className="mt-3 flex flex-wrap items-end gap-3 border-t border-neutral-100 pt-3 dark:border-neutral-800">
                            <label className="flex-1">
                                <span className="text-xs text-neutral-500">{t('admin.orders.show.noteLabel')}</span>
                                <input
                                    value={note}
                                    onChange={(e) => setNote(e.target.value)}
                                    className="mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                />
                            </label>
                            <Button variant="warning" icon={Ban} disabled={busy} onClick={() => onAction('unavailable', { note }, t('admin.orders.show.unavailableMsg'))}>
                                {t('admin.orders.show.markUnavailable')}
                            </Button>
                        </div>
                    )}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <SectionHeader icon={Package}>{t('admin.orders.show.items')}</SectionHeader>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-neutral-50 text-left text-neutral-600 dark:bg-neutral-800/50 dark:text-neutral-300">
                                    <tr>
                                        <th className="py-1 font-medium">{t('admin.common.product')}</th>
                                        <th className="py-1 font-medium">{t('admin.common.sku')}</th>
                                        <th className="py-1 text-right font-medium">{t('admin.common.price')}</th>
                                        <th className="py-1 text-right font-medium">{t('admin.common.qty')}</th>
                                        <th className="py-1 text-right font-medium">{t('admin.common.total')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {order.items.map((item, i) => (
                                        <tr key={i} className="border-t border-neutral-100 dark:border-neutral-800">
                                            <td className="py-2" dir="auto">{item.name}</td>
                                            <td className="py-2 font-mono text-neutral-500">{item.sku ?? '—'}</td>
                                            <td className="py-2 text-right">{item.unit_price}</td>
                                            <td className="py-2 text-right">{item.quantity}</td>
                                            <td className="py-2 text-right">{item.line_total}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="mt-3 border-t border-neutral-200 pt-3 dark:border-neutral-800">
                            <Row label={t('admin.common.subtotal')} value={`${order.subtotal} ${order.currency}`} />
                            {order.discount_total > 0 && <Row label={t('admin.common.discount')} value={`−${order.discount_total} ${order.currency}`} />}
                            <Row label={t('admin.common.shipping')} value={`${order.shipping_fee} ${order.currency}`} />
                            <div className="flex justify-between pt-1 font-bold">
                                <span>{t('admin.common.total')}</span>
                                <span>{order.total} {order.currency}</span>
                            </div>
                        </div>
                    </section>

                    <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <SectionHeader icon={History}>{t('admin.orders.show.activity')}</SectionHeader>
                        {order.activities.length === 0 ? (
                            <p className="text-sm text-neutral-400">{t('admin.orders.show.noActivity')}</p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {order.activities.map((a, i) => (
                                    <li key={i} className="flex justify-between gap-3 border-b border-neutral-100 pb-2 last:border-0 dark:border-neutral-800">
                                        <span>
                                            {a.type === 'status_change' ? (
                                                <>
                                                    {a.from_status ? t(`status.${a.from_status}`) : '—'} → <b>{a.to_status ? t(`status.${a.to_status}`) : ''}</b>
                                                </>
                                            ) : (
                                                a.type
                                            )}
                                            {a.note && <span className="text-neutral-500"> ({a.note})</span>}
                                            {a.user && <span className="text-neutral-400"> {t('admin.common.by', { user: a.user })}</span>}
                                        </span>
                                        <span className="shrink-0 text-neutral-400">{a.created_at}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

                <div className="space-y-6">
                    <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <SectionHeader icon={User}>{t('admin.common.customer')}</SectionHeader>
                        <Row icon={User} label={t('admin.common.name')} value={order.customer_name ?? '—'} />
                        <Row icon={Phone} label={t('admin.common.phone')} value={order.customer_phone ?? '—'} />
                        <Row icon={Mail} label={t('admin.common.email')} value={order.customer_email ?? '—'} />
                    </section>

                    <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <SectionHeader icon={Truck}>{t('admin.common.shipping')}</SectionHeader>
                        <Row icon={Globe} label={t('admin.common.country')} value={addr.country ?? '—'} />
                        <Row icon={Building2} label={t('admin.common.city')} value={addr.city ?? '—'} />
                        {addr.district && <Row icon={MapPin} label={t('admin.common.district')} value={addr.district} />}
                        {addr.street && <Row icon={Signpost} label={t('admin.common.street')} value={addr.street} />}
                        {addr.building && <Row icon={Building2} label={t('admin.common.building')} value={addr.building} />}
                        <Row icon={Truck} label={t('admin.common.carrier')} value={order.carrier ?? '—'} />
                        <Row icon={PackageSearch} label={t('admin.common.tracking')} value={order.tracking_number ?? '—'} />
                    </section>

                    <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <SectionHeader icon={CreditCard}>{t('admin.common.payment')}</SectionHeader>
                        <Row icon={CreditCard} label={t('admin.common.method')} value={order.payment_method ? t(`admin.paymentMethod.${order.payment_method}`) : '—'} />
                        <Row icon={CircleDollarSign} label={t('admin.common.status')} value={<PaymentStatusBadge status={order.payment_status} />} />
                    </section>

                    {(order.confirmed_by || order.confirmed_at || order.delivered_at || order.admin_notes) && (
                        <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                            <SectionHeader icon={ShieldCheck}>{t('admin.orders.show.admin')}</SectionHeader>
                            {order.confirmed_by && <Row icon={UserCheck} label={t('admin.orders.show.confirmedBy')} value={order.confirmed_by} />}
                            {order.confirmed_at && <Row icon={CalendarCheck} label={t('admin.orders.show.confirmedAt')} value={order.confirmed_at} />}
                            {order.delivered_at && <Row icon={PackageCheck} label={t('admin.orders.show.deliveredAt')} value={order.delivered_at} />}
                            {order.admin_notes && <p className="mt-2 text-sm text-neutral-500">{order.admin_notes}</p>}
                        </section>
                    )}
                </div>
            </div>
        </div>
    );
}
