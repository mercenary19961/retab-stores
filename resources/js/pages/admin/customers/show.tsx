import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, Gift, Languages, Mail, MapPin, MessageCircle, Phone, ShoppingBag, User, type LucideIcon } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
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
    const { t } = useAdminT();
    const displayName = customer.name ?? t('admin.customers.show.headTitle', { id: customer.id });

    return (
        <AdminLayout title={displayName}>
            <Head title={displayName} />

            <div className="mb-4">
                <Link href="/admin/customers" className="inline-flex items-center gap-1 text-sm text-neutral-500 underline">
                    <ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {t('admin.nav.customers')}
                </Link>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-1">
                    <section className="rounded-lg border border-neutral-200 bg-white p-5 text-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-3 flex items-center gap-2 font-bold" dir="auto">
                            <User className="h-4 w-4 shrink-0 text-brand-gold" /> {customer.name ?? '—'}
                        </h2>
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

                    <section className="rounded-lg border border-neutral-200 bg-white p-5 text-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-1 flex items-center gap-2 font-bold">
                            <Gift className="h-4 w-4 text-brand-gold" /> {t('admin.customers.show.loyalty')}
                        </h2>
                        <p className="mb-3 text-neutral-500">
                            {t('admin.customers.show.loyaltyProgress', { confirmed: loyalty.confirmed_purchases, progress: loyalty.progress, milestone: loyalty.milestone })}
                        </p>
                        <div className="mb-4 flex gap-1.5">
                            {Array.from({ length: loyalty.milestone }).map((_, i) => (
                                <div key={i} className={`h-2.5 flex-1 rounded-full ${i < loyalty.progress ? 'bg-neutral-900 dark:bg-white' : 'bg-neutral-200 dark:bg-neutral-700'}`} />
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

                <section className="rounded-lg border border-neutral-200 bg-white p-5 lg:col-span-2 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="mb-3 flex items-center gap-2 font-bold">
                        <ShoppingBag className="h-4 w-4 text-brand-gold" /> {t('admin.customers.show.orders')}
                    </h2>
                    {orders.length === 0 ? (
                        <p className="text-sm text-neutral-400">{t('admin.orders.empty')}</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="border-b border-neutral-200 bg-neutral-50 text-left text-neutral-600 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-300">
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
                                            <Link href={`/admin/orders/${o.order_number}`} className="font-mono text-blue-600 underline dark:text-blue-400">
                                                {o.order_number}
                                            </Link>
                                        </td>
                                        <td className="py-2">{t(`status.${o.status}`)}</td>
                                        <td className="py-2">{o.payment_status}</td>
                                        <td className="py-2">{o.total.toFixed(2)} {t('admin.common.sar')}</td>
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
