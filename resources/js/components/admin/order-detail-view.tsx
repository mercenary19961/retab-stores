import Button from '@/components/admin/button';
import Modal from '@/components/admin/modal';
import ShippingPicker from '@/components/admin/shipping-picker';
import StatusBadge from '@/components/admin/status-badge';
import CopyText from '@/components/copy-text';
import { useAdminT } from '@/i18n/use-admin-t';
import { THEAD } from '@/lib/admin-ui';
import { isolate } from '@/lib/bidi';
import type { TFunction } from 'i18next';
import {
    Ban,
    Building2,
    CalendarCheck,
    Check,
    CircleDollarSign,
    CreditCard,
    ExternalLink,
    FileText,
    Globe,
    History,
    Landmark,
    Mail,
    MapPin,
    MessageCircle,
    Package,
    PackageCheck,
    PackageSearch,
    PackageX,
    Phone,
    Printer,
    Send,
    ShieldCheck,
    Signpost,
    StickyNote,
    Truck,
    User,
    UserCheck,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';

export interface OrderItem {
    name: string;
    /** The chosen size/packaging (e.g. «كرتون»), snapshotted onto the line. */
    option: string | null;
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
    /**
     * Shipping entries carry the carrier, tracking number and the store's cost;
     * payment entries carry the gateway, the amount and the gateway's own id.
     * `cost` is what a CARRIER charged the store, `amount` is what a CUSTOMER
     * paid — they are deliberately separate keys and must not be conflated.
     */
    meta: {
        tracking_number?: string;
        carrier?: string;
        cost?: number;
        currency?: string;
        gateway?: string;
        amount?: number;
        transaction_id?: string;
        method?: string;
    } | null;
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
    /** What the carrier charged the store. Null when unknown or not yet shipped. */
    shipping_cost: number | null;
    total: number;
    currency: string;
    tracking_number: string | null;
    carrier: string | null;
    /** The carrier's public tracking page, when the shipping portal has one on file. */
    tracking_url: string | null;
    shipping_label_url: string | null;
    whatsapp_url: string | null;
    /** The account the customer was told to pay into. Only while a transfer is awaited. */
    bank_name: string | null;
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
    /** Cancel the ORDER (refunds it). Only before the admin confirms. */
    cancel: boolean;
    /** Recall the SHIPMENT and return the order to confirmed. Moves no money. */
    cancelShipment: boolean;
    sendPaymentLink: boolean;
    /** A bank transfer is awaited: staff record it once it shows in the account. */
    markTransferReceived: boolean;
    editNotes: boolean;
}

/**
 * One sentence saying what this order needs next, shown beside its actions.
 * The status pill says where the order IS; this says what to DO about it, which
 * is the question staff actually open the page with.
 */
function nextStep(order: OrderDetailData, t: TFunction): string {
    const key = (k: string, opts?: Record<string, unknown>) => t(`admin.orders.show.next.${k}`, opts);

    switch (order.status) {
        case 'pending_payment':
            if (order.payment_method === 'bank_transfer') {
                return key('bankTransfer', {
                    amount: isolate(`${order.total.toFixed(2)} ${order.currency}`),
                    bank: isolate(order.bank_name ?? '—'),
                    number: isolate(order.order_number),
                });
            }
            return order.payment_method ? key('gateway') : key('pendingPayment');
        case 'awaiting_confirmation':
            return key('awaiting');
        case 'confirmed':
            return key('confirmed');
        case 'shipped':
            return key('shipped', { carrier: isolate(order.carrier ?? '—'), tracking: isolate(order.tracking_number ?? '—') });
        case 'delivered':
            return key('delivered');
        case 'cancelled':
            return key('cancelled');
        case 'unavailable':
            return key('unavailable');
        default:
            return '';
    }
}

/** An outbound link styled like a small secondary button (opens in a new tab). */
function ToolLink({ href, icon: Icon, children }: { href: string; icon: LucideIcon; children: ReactNode }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-semibold text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
        >
            <Icon className="h-3.5 w-3.5" />
            {children}
        </a>
    );
}

/**
 * One line of the order timeline.
 *
 * 🔑 A switch rather than a longer ternary chain, because the chain ended in a
 * bare `a.type` fallback — which is exactly why `payment_lapsed` and
 * `payment_link_sent` rendered as raw snake_case for months: an unhandled type
 * failed silently instead of visibly. Anything unknown still degrades to the raw
 * type, but every type the server actually writes is now covered here.
 *
 * ⚠️ Adding a new activity type on the server means adding it here too. There is
 * no gate that catches the omission.
 */
function activityLabel(a: OrderActivity, t: TFunction): ReactNode {
    const line = (key: string, opts?: Record<string, unknown>) => <b>{t(`admin.orders.show.${key}`, opts)}</b>;

    switch (a.type) {
        case 'status_change':
            return (
                <>
                    {a.from_status ? t(`status.${a.from_status}`) : '—'} → <b>{a.to_status ? t(`status.${a.to_status}`) : ''}</b>
                </>
            );
        case 'tracking':
            return line('activityShipped', { carrier: a.meta?.carrier ?? '—' });
        case 'shipment_cancelled':
            return line('activityShipmentCancelled', { carrier: a.meta?.carrier ?? '—' });
        case 'payment_received':
            // `bank_transfer` has a translated label; gateway names (moyasar)
            // have none and fall through as written.
            return line('activityPaymentReceived', {
                gateway: a.meta?.gateway ? t(`admin.paymentMethod.${a.meta.gateway}`, { defaultValue: a.meta.gateway }) : '—',
            });
        case 'payment_authorized':
            return line('activityPaymentAuthorized', { gateway: a.meta?.gateway ?? '—' });
        case 'payment_lapsed':
            return line('activityPaymentLapsed');
        case 'payment_link_sent':
            return line('activityPaymentLinkSent');
        default:
            return a.type;
    }
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
            <Icon className="text-brand-gold h-4 w-4" />
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
    const [reference, setReference] = useState('');
    const [dialog, setDialog] = useState<'transfer' | 'unavailable' | null>(null);
    const [notes, setNotes] = useState(order.admin_notes ?? '');
    const [picking, setPicking] = useState(false);
    const addr = order.shipping_address ?? {};
    const hasActions =
        can.markTransferReceived || can.confirm || can.unavailable || can.ship || can.cancel || can.cancelShipment || can.sendPaymentLink;
    const hint = nextStep(order, t);
    const notesDirty = notes.trim() !== (order.admin_notes ?? '').trim();

    /** Close the dialog first, so it is not left open over the page while the request runs. */
    const act = (verb: string, data?: Record<string, string>) => {
        setDialog(null);
        onAction(verb, data);
    };

    const ship = (deliveryOptionId: number | null) => {
        setPicking(false);
        // Omitted entirely rather than sent as null: the server treats an absent
        // delivery_option_id as "choose the cheapest".
        onAction('ship', deliveryOptionId === null ? {} : { delivery_option_id: String(deliveryOptionId) });
    };

    return (
        <div className="space-y-6">
            {/* Mounted only while open so each opening refetches rates — they are
                live prices and a cached list would ship the wrong carrier. */}
            {picking && (
                <ShippingPicker open={picking} onClose={() => setPicking(false)} orderNumber={order.order_number} onConfirm={ship} busy={busy} />
            )}

            <Modal open={dialog === 'transfer'} onClose={() => setDialog(null)} title={t('admin.orders.show.transferTitle')} size="sm">
                <p className="text-sm text-neutral-600 dark:text-neutral-300">
                    {t('admin.orders.show.transferBody', { amount: isolate(`${order.total.toFixed(2)} ${order.currency}`) })}
                </p>
                <label className="mt-4 block">
                    <span className="text-xs font-medium text-neutral-500">{t('admin.orders.show.transferReference')}</span>
                    <input
                        value={reference}
                        onChange={(e) => setReference(e.target.value)}
                        dir="ltr"
                        maxLength={100}
                        className="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 font-mono text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <span className="mt-1 block text-xs text-neutral-500">{t('admin.orders.show.transferReferenceHint')}</span>
                </label>
                <div className="mt-5 flex justify-end gap-2">
                    <Button variant="ghost" onClick={() => setDialog(null)}>
                        {t('admin.orders.show.back')}
                    </Button>
                    <Button
                        variant="primary"
                        icon={Landmark}
                        disabled={busy}
                        onClick={() => act('transfer-received', { reference: reference.trim() })}
                    >
                        {t('admin.orders.show.markTransferReceived')}
                    </Button>
                </div>
            </Modal>

            <Modal open={dialog === 'unavailable'} onClose={() => setDialog(null)} title={t('admin.orders.show.unavailableTitle')} size="sm">
                <p className="text-sm text-neutral-600 dark:text-neutral-300">{t('admin.orders.show.unavailableBody')}</p>
                <label className="mt-4 block">
                    <span className="text-xs font-medium text-neutral-500">{t('admin.orders.show.noteLabel')}</span>
                    <textarea
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        rows={3}
                        maxLength={1000}
                        className="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                </label>
                <div className="mt-5 flex justify-end gap-2">
                    <Button variant="ghost" onClick={() => setDialog(null)}>
                        {t('admin.orders.show.back')}
                    </Button>
                    <Button variant="warning" icon={Ban} disabled={busy} onClick={() => act('unavailable', { note })}>
                        {t('admin.orders.show.markUnavailable')}
                    </Button>
                </div>
            </Modal>

            {/* Where the order is, then the tools that are useful in any state. */}
            <div className="flex flex-wrap items-center gap-3">
                <StatusBadge domain="order" value={order.status} className="px-2.5 py-1 text-sm" />
                <span className="text-sm text-neutral-400">{order.created_at ?? '—'}</span>
                <div className="ms-auto flex flex-wrap items-center gap-2">
                    {order.whatsapp_url && (
                        <ToolLink href={order.whatsapp_url} icon={MessageCircle}>
                            {t('admin.orders.show.whatsapp')}
                        </ToolLink>
                    )}
                    <ToolLink href={`/admin/orders/${order.order_number}/packing-slip`} icon={Printer}>
                        {t('admin.orders.show.packingSlip')}
                    </ToolLink>
                    {order.tracking_url && (
                        <ToolLink href={order.tracking_url} icon={ExternalLink}>
                            {t('admin.orders.show.trackParcel')}
                        </ToolLink>
                    )}
                    {order.shipping_label_url && (
                        <ToolLink href={order.shipping_label_url} icon={FileText}>
                            {t('admin.orders.show.shippingLabel')}
                        </ToolLink>
                    )}
                </div>
            </div>

            {/* What the order needs next, with the buttons that do it. The sentence
                fills the width the lone button used to leave empty. The main action
                sits at the inline end; Cancel is never the first thing you reach. */}
            {hint && (
                <div className="flex flex-wrap items-center gap-4 rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="min-w-0 flex-1 basis-72">
                        <p className="text-xs font-semibold tracking-wide text-neutral-500 uppercase">{t('admin.orders.show.nextStep')}</p>
                        <p className="mt-1 text-sm text-neutral-800 dark:text-neutral-200">{hint}</p>
                    </div>

                    {hasActions && (
                        <div className="flex flex-wrap items-center gap-2">
                            {can.cancel && (
                                <Button
                                    variant="danger"
                                    icon={X}
                                    disabled={busy}
                                    onClick={() => onAction('cancel', {}, t('admin.orders.show.cancelMsg'))}
                                >
                                    {t('admin.orders.show.cancel')}
                                </Button>
                            )}
                            {/* Recalls the parcel so the order can be shipped again — NOT
                                the same as cancelling the order, so it is deliberately
                                styled as a secondary action and worded differently. */}
                            {can.cancelShipment && (
                                <Button
                                    variant="secondary"
                                    icon={PackageX}
                                    disabled={busy}
                                    onClick={() => onAction('cancel-shipment', {}, t('admin.orders.show.cancelShipmentMsg'))}
                                >
                                    {t('admin.orders.show.cancelShipment')}
                                </Button>
                            )}
                            {can.unavailable && (
                                <Button variant="warning" icon={Ban} disabled={busy} onClick={() => setDialog('unavailable')}>
                                    {t('admin.orders.show.markUnavailable')}
                                </Button>
                            )}
                            {/* The hold lapsed before anyone confirmed. This recovers the
                                sale rather than ending it. */}
                            {can.sendPaymentLink && (
                                <Button
                                    variant="primary"
                                    icon={Send}
                                    disabled={busy}
                                    onClick={() => onAction('payment-link', {}, t('admin.orders.show.paymentLinkMsg'))}
                                >
                                    {t('admin.orders.show.paymentLink')}
                                </Button>
                            )}
                            {can.markTransferReceived && (
                                <Button variant="primary" icon={Landmark} disabled={busy} onClick={() => setDialog('transfer')}>
                                    {t('admin.orders.show.markTransferReceived')}
                                </Button>
                            )}
                            {can.confirm && (
                                <Button
                                    variant="success"
                                    icon={Check}
                                    disabled={busy}
                                    onClick={() => onAction('confirm', {}, t('admin.orders.show.confirmMsg'))}
                                >
                                    {t('admin.orders.show.confirmOrder')}
                                </Button>
                            )}
                            {can.ship && (
                                <Button variant="primary" icon={Truck} disabled={busy} onClick={() => setPicking(true)}>
                                    {t('admin.orders.show.ship')}
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <SectionHeader icon={Package}>{t('admin.orders.show.items')}</SectionHeader>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className={THEAD}>
                                    <tr>
                                        <th className="px-3 py-2 text-start font-medium">{t('admin.common.product')}</th>
                                        <th className="px-3 py-2 text-start font-medium">{t('admin.common.sku')}</th>
                                        <th className="px-3 py-2 text-end font-medium">{t('admin.common.price')}</th>
                                        <th className="px-3 py-2 text-end font-medium">{t('admin.common.qty')}</th>
                                        <th className="px-3 py-2 text-end font-medium">{t('admin.common.total')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {order.items.map((item, i) => (
                                        <tr key={i} className="border-t border-neutral-100 dark:border-neutral-800">
                                            {/* <bdi>, not dir="auto" on the cell: an Arabic name keeps its own
                                                direction without flipping the cell's alignment, which pushed it
                                                against the SKU column in the English panel. */}
                                            <td className="px-3 py-2 text-start">
                                                <bdi>{item.name}</bdi>
                                                {item.option && (
                                                    <span className="block text-xs text-neutral-500">
                                                        <bdi>{item.option}</bdi>
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-start font-mono whitespace-nowrap text-neutral-500">
                                                {item.sku ? (
                                                    <CopyText
                                                        value={item.sku}
                                                        copyLabel={t('admin.common.copy')}
                                                        copiedLabel={t('admin.common.copied')}
                                                    />
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-end tabular-nums">{item.unit_price}</td>
                                            <td className="px-3 py-2 text-end tabular-nums">{item.quantity}</td>
                                            <td className="px-3 py-2 text-end tabular-nums">{item.line_total}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="mt-3 border-t border-neutral-200 pt-3 dark:border-neutral-800">
                            <Row label={t('admin.common.subtotal')} value={`${order.subtotal} ${order.currency}`} />
                            {order.discount_total > 0 && (
                                <Row label={t('admin.common.discount')} value={`−${order.discount_total} ${order.currency}`} />
                            )}
                            <Row label={t('admin.common.shipping')} value={`${order.shipping_fee} ${order.currency}`} />
                            <div className="flex justify-between pt-1 font-bold">
                                <span>{t('admin.common.total')}</span>
                                <span>
                                    {order.total} {order.currency}
                                </span>
                            </div>
                        </div>
                    </section>

                    <section className="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <SectionHeader icon={History}>{t('admin.orders.show.activity')}</SectionHeader>
                        {order.activities.length === 0 ? (
                            <p className="text-sm text-neutral-400">{t('admin.orders.show.noActivity')}</p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {order.activities.map((a, i) => (
                                    <li
                                        key={i}
                                        className="flex justify-between gap-3 border-b border-neutral-100 pb-2 last:border-0 dark:border-neutral-800"
                                    >
                                        <span>
                                            {activityLabel(a, t)}
                                            {/* The detail that makes the row auditable: which parcel,
                                                and what the carrier charged us for it. */}
                                            {a.meta?.tracking_number && <span className="text-neutral-500"> · {a.meta.tracking_number}</span>}
                                            {/* What the CUSTOMER paid. Separate from `cost` above,
                                                which is what the carrier charged the store. */}
                                            {a.meta?.amount !== undefined && (
                                                <span className="text-neutral-500">
                                                    {' '}
                                                    · {a.meta.amount.toFixed(2)} {a.meta.currency ?? order.currency}
                                                </span>
                                            )}
                                            {a.meta?.cost !== undefined && (
                                                <span className="text-neutral-500">
                                                    {' '}
                                                    · {t('admin.orders.show.activityCost')} {a.meta.cost.toFixed(2)}{' '}
                                                    {a.meta.currency ?? order.currency}
                                                </span>
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
                    <section className="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <SectionHeader icon={User}>{t('admin.common.customer')}</SectionHeader>
                        <Row icon={User} label={t('admin.common.name')} value={order.customer_name ?? '—'} />
                        <Row icon={Phone} label={t('admin.common.phone')} value={order.customer_phone ?? '—'} />
                        <Row icon={Mail} label={t('admin.common.email')} value={order.customer_email ?? '—'} />
                    </section>

                    <section className="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <SectionHeader icon={Truck}>{t('admin.common.shipping')}</SectionHeader>
                        <Row icon={Globe} label={t('admin.common.country')} value={addr.country ?? '—'} />
                        <Row icon={Building2} label={t('admin.common.city')} value={addr.city ?? '—'} />
                        {addr.district && <Row icon={MapPin} label={t('admin.common.district')} value={addr.district} />}
                        {addr.street && <Row icon={Signpost} label={t('admin.common.street')} value={addr.street} />}
                        {addr.building && <Row icon={Building2} label={t('admin.common.building')} value={addr.building} />}
                        <Row icon={Truck} label={t('admin.common.carrier')} value={order.carrier ?? '—'} />
                        <Row icon={PackageSearch} label={t('admin.common.tracking')} value={order.tracking_number ?? '—'} />
                        {/* Cost vs fee: what the carrier charged us against the flat
                            rate the customer paid. The margin line is the whole
                            reason the cost is recorded — the flat-rate decision was
                            "we absorb the difference", and this is the difference.
                            Hidden entirely when unknown (never shipped, or shipped
                            before the cost was captured) rather than shown as 0. */}
                        {order.shipping_cost !== null && (
                            <>
                                <Row
                                    icon={CircleDollarSign}
                                    label={t('admin.orders.show.shippingCost')}
                                    value={`${order.shipping_cost.toFixed(2)} ${order.currency}`}
                                />
                                <Row
                                    icon={CircleDollarSign}
                                    label={t('admin.orders.show.shippingMargin')}
                                    value={
                                        <span className={order.shipping_fee - order.shipping_cost < 0 ? 'text-red-500' : undefined}>
                                            {(order.shipping_fee - order.shipping_cost).toFixed(2)} {order.currency}
                                        </span>
                                    }
                                />
                            </>
                        )}
                    </section>

                    <section className="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <SectionHeader icon={CreditCard}>{t('admin.common.payment')}</SectionHeader>
                        <Row
                            icon={CreditCard}
                            label={t('admin.common.method')}
                            value={order.payment_method ? t(`admin.paymentMethod.${order.payment_method}`) : '—'}
                        />
                        <Row
                            icon={CircleDollarSign}
                            label={t('admin.common.status')}
                            value={<StatusBadge domain="payment" value={order.payment_status} />}
                        />
                    </section>

                    {(can.editNotes || order.confirmed_by || order.confirmed_at || order.delivered_at || order.admin_notes) && (
                        <section className="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                            <SectionHeader icon={ShieldCheck}>{t('admin.orders.show.admin')}</SectionHeader>
                            {order.confirmed_by && <Row icon={UserCheck} label={t('admin.orders.show.confirmedBy')} value={order.confirmed_by} />}
                            {order.confirmed_at && <Row icon={CalendarCheck} label={t('admin.orders.show.confirmedAt')} value={order.confirmed_at} />}
                            {order.delivered_at && <Row icon={PackageCheck} label={t('admin.orders.show.deliveredAt')} value={order.delivered_at} />}

                            <div className="mt-3">
                                <p className="flex items-center gap-2 text-sm text-neutral-500">
                                    <StickyNote className="h-3.5 w-3.5 shrink-0" />
                                    {t('admin.orders.show.notes')}
                                </p>
                                {can.editNotes ? (
                                    <>
                                        <textarea
                                            value={notes}
                                            onChange={(e) => setNotes(e.target.value)}
                                            rows={3}
                                            maxLength={2000}
                                            dir="auto"
                                            placeholder={t('admin.orders.show.notesPlaceholder')}
                                            aria-label={t('admin.orders.show.notes')}
                                            className="mt-2 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                        />
                                        {/* Only offered once something changed, so the button
                                            says whether pressing it would do anything. */}
                                        <div className="mt-2 flex justify-end">
                                            <Button
                                                size="sm"
                                                variant="primary"
                                                disabled={busy || !notesDirty}
                                                onClick={() => onAction('notes', { admin_notes: notes })}
                                            >
                                                {t('admin.orders.show.notesSave')}
                                            </Button>
                                        </div>
                                    </>
                                ) : (
                                    <p className="mt-2 text-sm whitespace-pre-wrap text-neutral-600 dark:text-neutral-300" dir="auto">
                                        {order.admin_notes || t('admin.orders.show.notesEmpty')}
                                    </p>
                                )}
                            </div>
                        </section>
                    )}
                </div>
            </div>
        </div>
    );
}
