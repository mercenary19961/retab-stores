import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Boxes, Package, RotateCcw, ShoppingBag, Users, type LucideIcon } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import { useAdminT } from '@/i18n/use-admin-t';

interface Stats {
    awaitingConfirmation: number;
    pendingPayment: number;
    returnsToReview: number;
    lowStock: number;
    activeProducts: number;
    customers: number;
}

interface RecentOrder {
    order_number: string;
    customer_name: string;
    status: string;
    total: number;
    created_at: string | null;
}

type Card = { key: keyof Stats; href: string; icon: LucideIcon; attention?: boolean };

const CARDS: Card[] = [
    { key: 'awaitingConfirmation', href: '/admin/orders', icon: ShoppingBag, attention: true },
    { key: 'returnsToReview', href: '/admin/returns', icon: RotateCcw, attention: true },
    { key: 'pendingPayment', href: '/admin/orders', icon: AlertTriangle },
    { key: 'lowStock', href: '/admin/products', icon: Boxes, attention: true },
    { key: 'activeProducts', href: '/admin/products', icon: Package },
    { key: 'customers', href: '/admin/customers', icon: Users },
];

export default function AdminDashboard({ stats, recentOrders = [] }: { stats: Stats; recentOrders?: RecentOrder[] }) {
    const { t } = useAdminT();

    return (
        <AdminLayout title={t('admin.dashboard.title')}>
            <Head title={t('admin.dashboard.title')} />

            <p className="mb-6 text-sm text-neutral-400">{t('admin.dashboard.subtitle')}</p>

            {/* Stat cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {CARDS.map((c) => {
                    const value = stats[c.key];
                    const flag = Boolean(c.attention && value > 0);
                    const Icon = c.icon;
                    return (
                        <Link
                            key={c.key}
                            href={c.href}
                            className={
                                flag
                                    ? 'flex items-center justify-between rounded-xl border border-brand-gold/40 bg-brand-gold/10 p-5 transition-colors hover:border-brand-gold'
                                    : 'flex items-center justify-between rounded-xl border border-neutral-800 bg-neutral-900 p-5 transition-colors hover:border-neutral-700'
                            }
                        >
                            <div>
                                <div className={flag ? 'text-3xl font-bold text-brand-gold' : 'text-3xl font-bold text-neutral-100'}>
                                    {value}
                                </div>
                                <div className="mt-1 text-sm text-neutral-400">{t(`admin.dashboard.cards.${c.key}`)}</div>
                            </div>
                            <Icon className={flag ? 'h-8 w-8 text-brand-gold' : 'h-8 w-8 text-neutral-600'} />
                        </Link>
                    );
                })}
            </div>

            {/* Recent orders */}
            <div className="mt-8 overflow-hidden rounded-xl border border-neutral-800 bg-neutral-900">
                <div className="flex items-center justify-between border-b border-neutral-800 px-5 py-3">
                    <h2 className="font-semibold text-neutral-100">{t('admin.dashboard.recentOrders')}</h2>
                    <Link href="/admin/orders" className="text-sm text-brand-gold hover:underline">
                        {t('admin.dashboard.viewAll')}
                    </Link>
                </div>

                {recentOrders.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-neutral-500">{t('admin.dashboard.noRecentOrders')}</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="text-start text-neutral-500">
                            <tr className="border-b border-neutral-800">
                                <th className="px-5 py-3 text-start font-medium">{t('admin.dashboard.order')}</th>
                                <th className="px-5 py-3 text-start font-medium">{t('admin.dashboard.customers')}</th>
                                <th className="px-5 py-3 text-start font-medium">{t('admin.dashboard.status')}</th>
                                <th className="px-5 py-3 text-start font-medium">{t('admin.dashboard.total')}</th>
                                <th className="px-5 py-3 text-start font-medium">{t('admin.dashboard.date')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {recentOrders.map((o) => (
                                <tr key={o.order_number} className="border-b border-neutral-800 last:border-0">
                                    <td className="px-5 py-3">
                                        <Link href={`/admin/orders/${o.order_number}`} className="font-semibold text-brand-gold hover:underline">
                                            {o.order_number}
                                        </Link>
                                    </td>
                                    <td className="px-5 py-3 text-neutral-300" dir="auto">{o.customer_name}</td>
                                    <td className="px-5 py-3">
                                        <span className="rounded-full bg-neutral-800 px-2.5 py-0.5 text-xs text-neutral-300">
                                            {o.status.replace(/_/g, ' ')}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-neutral-300" dir="ltr">{o.total.toFixed(2)} SAR</td>
                                    <td className="px-5 py-3 text-neutral-400" dir="ltr">{o.created_at ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </AdminLayout>
    );
}
