import { Head, Link } from '@inertiajs/react';
import {
    Banknote,
    BarChart3,
    BellRing,
    Boxes,
    Clock,
    Gift,
    MessageCircle,
    PackageX,
    RotateCcw,
    ShoppingBag,
    TrendingDown,
    TrendingUp,
    Trophy,
    Truck,
    UserPlus,
    Users,
    type LucideIcon,
} from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import { useAdminT } from '@/i18n/use-admin-t';

interface Kpis {
    currency: string;
    revenue30: number;
    revenuePrev30: number;
    orders30: number;
    ordersPrev30: number;
    revenueToday: number;
    revenueYesterday: number;
}
interface TrendPoint {
    date: string;
    revenue: number;
    orders: number;
}
interface Task {
    key: string;
    count: number;
    href: string;
    urgent: boolean;
}
interface LowStockRow {
    id: number;
    name_ar: string;
    name_en: string | null;
    sku: string;
    stock: number;
}
interface Inventory {
    lastSynced: { at: string | null; minutes: number | null; stale: boolean };
    outOfStock: number;
    lowStock: number;
    activeProducts: number;
    lowStockList: LowStockRow[];
}
interface TopProduct {
    product_id: number;
    name_ar: string;
    name_en: string | null;
    qty: number;
    revenue: number;
}
interface DemandRow {
    product_id: number;
    name_ar: string | null;
    name_en: string | null;
    count: number;
}
interface Customers {
    total: number;
    new30: number;
    whatsappAudience: number;
    nearReward: number;
}
interface RecentOrder {
    order_number: string;
    customer_name: string;
    status: string;
    total: number;
    created_at: string | null;
}

const TASK_ICON: Record<string, LucideIcon> = {
    awaitingConfirmation: ShoppingBag,
    bankTransfers: Banknote,
    returnsToReview: RotateCcw,
    readyToShip: Truck,
    tamaraExpiring: Clock,
};

/**
 * Friendly "N minutes/hours/days/weeks ago" for the last-sync banner, localized
 * and pluralized by the browser (respects the admin language toggle).
 */
function syncedAgo(minutes: number, locale: string): string {
    const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    if (minutes < 60) return rtf.format(-minutes, 'minute');
    const hours = Math.round(minutes / 60);
    if (hours < 24) return rtf.format(-hours, 'hour');
    const days = Math.round(hours / 24);
    if (days < 14) return rtf.format(-days, 'day');
    return rtf.format(-Math.round(days / 7), 'week');
}

export default function AdminDashboard({
    kpis,
    trend,
    tasks,
    inventory,
    insights,
    customers,
    recentOrders = [],
}: {
    kpis: Kpis;
    trend: TrendPoint[];
    tasks: Task[];
    inventory: Inventory;
    insights: { topProducts: TopProduct[]; demand: DemandRow[] };
    customers: Customers;
    recentOrders?: RecentOrder[];
}) {
    const { t, i18n } = useAdminT();
    const loc = (ar: string | null, en: string | null) => (i18n.language === 'en' && en ? en : (ar ?? '—'));
    const sar = t('admin.common.sar');
    const money = (n: number) => `${Math.round(n).toLocaleString()} ${sar}`;

    const delta = (cur: number, prev: number): { pct: number | null; up: boolean } => {
        if (prev <= 0) return { pct: cur > 0 ? 100 : null, up: cur > 0 };
        return { pct: ((cur - prev) / prev) * 100, up: cur >= prev };
    };

    const aov = kpis.orders30 ? kpis.revenue30 / kpis.orders30 : 0;
    const aovPrev = kpis.ordersPrev30 ? kpis.revenuePrev30 / kpis.ordersPrev30 : 0;
    const trendMax = Math.max(1, ...trend.map((p) => p.revenue));
    const openTasks = tasks.filter((task) => task.count > 0);

    const KpiCard = ({ label, value, cur, prev, sub }: { label: string; value: string; cur: number; prev: number; sub: string }) => {
        const d = delta(cur, prev);
        return (
            <div className="rounded-xl border border-neutral-800 bg-neutral-900 p-5">
                <div className="text-sm text-neutral-400">{label}</div>
                <div className="mt-1 text-2xl font-bold text-neutral-100">{value}</div>
                <div className="mt-1 flex items-center gap-1.5 text-xs">
                    {d.pct === null ? (
                        <span className="text-neutral-500">—</span>
                    ) : (
                        <span className={`inline-flex items-center gap-0.5 font-medium ${d.up ? 'text-green-400' : 'text-red-400'}`}>
                            {d.up ? <TrendingUp className="h-3.5 w-3.5" /> : <TrendingDown className="h-3.5 w-3.5" />}
                            {Math.abs(d.pct).toFixed(0)}%
                        </span>
                    )}
                    <span className="text-neutral-500">{sub}</span>
                </div>
            </div>
        );
    };

    return (
        <AdminLayout title={t('admin.dashboard.title')}>
            <Head title={t('admin.dashboard.title')} />

            <p className="mb-6 text-sm text-neutral-400">{t('admin.dashboard.subtitle')}</p>

            {/* Revenue KPIs */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCard label={t('admin.dashboard.kpis.revenue30')} value={money(kpis.revenue30)} cur={kpis.revenue30} prev={kpis.revenuePrev30} sub={t('admin.dashboard.kpis.vsPrev')} />
                <KpiCard label={t('admin.dashboard.kpis.orders30')} value={kpis.orders30.toLocaleString()} cur={kpis.orders30} prev={kpis.ordersPrev30} sub={t('admin.dashboard.kpis.vsPrev')} />
                <KpiCard label={t('admin.dashboard.kpis.aov')} value={money(aov)} cur={aov} prev={aovPrev} sub={t('admin.dashboard.kpis.vsPrev')} />
                <KpiCard label={t('admin.dashboard.kpis.revenueToday')} value={money(kpis.revenueToday)} cur={kpis.revenueToday} prev={kpis.revenueYesterday} sub={t('admin.dashboard.kpis.vsYesterday')} />
            </div>

            {/* Daily revenue trend */}
            <div className="mt-6 rounded-xl border border-neutral-800 bg-neutral-900 p-5">
                <h2 className="mb-4 flex items-center gap-2 font-semibold text-neutral-100"><BarChart3 className="h-4 w-4 text-brand-gold" /> {t('admin.dashboard.trend.title')}</h2>
                {trendMax <= 1 ? (
                    <p className="py-8 text-center text-sm text-neutral-500">{t('admin.dashboard.trend.empty')}</p>
                ) : (
                    <div className="flex h-36 items-end gap-1" dir="ltr">
                        {trend.map((p) => (
                            <div
                                key={p.date}
                                title={`${p.date} · ${money(p.revenue)} · ${p.orders}`}
                                className="group flex-1"
                            >
                                <div
                                    className="w-full rounded-t bg-brand-gold/50 transition-colors group-hover:bg-brand-gold"
                                    style={{ height: `${Math.max(2, (p.revenue / trendMax) * 100)}%` }}
                                />
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Needs attention */}
            <div className="mt-6 rounded-xl border border-neutral-800 bg-neutral-900 p-5">
                <h2 className="mb-4 flex items-center gap-2 font-semibold text-neutral-100"><BellRing className="h-4 w-4 text-brand-gold" /> {t('admin.dashboard.tasks.title')}</h2>
                {openTasks.length === 0 ? (
                    <p className="text-sm text-neutral-500">{t('admin.dashboard.tasks.allClear')}</p>
                ) : (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {openTasks.map((task) => {
                            const Icon = TASK_ICON[task.key] ?? ShoppingBag;
                            return (
                                <Link
                                    key={task.key}
                                    href={task.href}
                                    className={`flex items-center gap-3 rounded-lg border p-3 transition-colors ${
                                        task.urgent
                                            ? 'border-amber-500/40 bg-amber-500/10 hover:border-amber-500'
                                            : 'border-neutral-800 bg-neutral-950/40 hover:border-neutral-700'
                                    }`}
                                >
                                    <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${task.urgent ? 'bg-amber-500/20 text-amber-300' : 'bg-neutral-800 text-neutral-300'}`}>
                                        <Icon className="h-5 w-5" />
                                    </span>
                                    <div className="min-w-0">
                                        <div className="text-lg font-bold text-neutral-100">{task.count}</div>
                                        <div className="truncate text-xs text-neutral-400">{t(`admin.dashboard.tasks.${task.key}`)}</div>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Inventory health + Insights */}
            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Inventory */}
                <section className="rounded-xl border border-neutral-800 bg-neutral-900 p-5">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="flex items-center gap-2 font-semibold text-neutral-100"><Boxes className="h-4 w-4 text-brand-gold" /> {t('admin.dashboard.inventory.title')}</h2>
                        <Link href="/admin/stock-import" className="text-xs text-brand-gold hover:underline">{t('admin.dashboard.viewAll')}</Link>
                    </div>

                    <div
                        className={`mb-4 rounded-lg border px-3 py-2 text-xs ${
                            inventory.lastSynced.stale
                                ? 'border-amber-500/40 bg-amber-500/10 text-amber-300'
                                : 'border-neutral-800 bg-neutral-950/40 text-neutral-400'
                        }`}
                    >
                        {inventory.lastSynced.at === null || inventory.lastSynced.minutes === null
                            ? t('admin.dashboard.inventory.syncNever')
                            : inventory.lastSynced.stale
                              ? t('admin.dashboard.inventory.syncStale', { ago: syncedAgo(inventory.lastSynced.minutes, i18n.language) })
                              : t('admin.dashboard.inventory.syncOk', { ago: syncedAgo(inventory.lastSynced.minutes, i18n.language) })}
                    </div>

                    <div className="mb-4 grid grid-cols-3 gap-3 text-center">
                        <div className="rounded-lg bg-neutral-950/40 p-3">
                            <div className={`text-xl font-bold ${inventory.outOfStock ? 'text-red-400' : 'text-neutral-100'}`}>{inventory.outOfStock}</div>
                            <div className="text-xs text-neutral-500">{t('admin.dashboard.inventory.outOfStock')}</div>
                        </div>
                        <div className="rounded-lg bg-neutral-950/40 p-3">
                            <div className={`text-xl font-bold ${inventory.lowStock ? 'text-amber-400' : 'text-neutral-100'}`}>{inventory.lowStock}</div>
                            <div className="text-xs text-neutral-500">{t('admin.dashboard.inventory.lowStock')}</div>
                        </div>
                        <div className="rounded-lg bg-neutral-950/40 p-3">
                            <div className="text-xl font-bold text-neutral-100">{inventory.activeProducts}</div>
                            <div className="text-xs text-neutral-500">{t('admin.dashboard.inventory.active')}</div>
                        </div>
                    </div>

                    <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">{t('admin.dashboard.inventory.lowStockList')}</h3>
                    {inventory.lowStockList.length === 0 ? (
                        <p className="text-sm text-neutral-500">{t('admin.dashboard.inventory.allStocked')}</p>
                    ) : (
                        <ul className="space-y-1 text-sm">
                            {inventory.lowStockList.map((p) => (
                                <li key={p.id} className="flex items-center justify-between gap-2 border-b border-neutral-800/60 py-1.5 last:border-0">
                                    <Link href={`/admin/products/${p.id}/edit`} className="min-w-0 truncate text-neutral-200 hover:text-brand-gold" dir="auto">
                                        {loc(p.name_ar, p.name_en)}
                                    </Link>
                                    <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold ${p.stock <= 0 ? 'bg-red-500/15 text-red-300' : 'bg-amber-500/15 text-amber-300'}`}>
                                        {t('admin.dashboard.inventory.units', { count: p.stock })}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {/* Insights */}
                <section className="rounded-xl border border-neutral-800 bg-neutral-900 p-5">
                    <h2 className="mb-3 flex items-center gap-2 font-semibold text-neutral-100"><Trophy className="h-4 w-4 text-brand-gold" /> {t('admin.dashboard.insights.topProducts')}</h2>
                    {insights.topProducts.length === 0 ? (
                        <p className="mb-5 text-sm text-neutral-500">{t('admin.dashboard.insights.noSales')}</p>
                    ) : (
                        <ul className="mb-5 space-y-1 text-sm">
                            {insights.topProducts.map((p) => (
                                <li key={p.product_id} className="flex items-center justify-between gap-2 border-b border-neutral-800/60 py-1.5 last:border-0">
                                    <Link href={`/admin/products/${p.product_id}/edit`} className="min-w-0 truncate text-neutral-200 hover:text-brand-gold" dir="auto">
                                        {loc(p.name_ar, p.name_en)}
                                    </Link>
                                    <span className="shrink-0 text-xs text-neutral-400">
                                        {t('admin.dashboard.insights.sold', { count: p.qty })} · {money(p.revenue)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}

                    <h3 className="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <PackageX className="h-3.5 w-3.5" /> {t('admin.dashboard.insights.demand')}
                    </h3>
                    {insights.demand.length === 0 ? (
                        <p className="text-sm text-neutral-500">{t('admin.dashboard.insights.noDemand')}</p>
                    ) : (
                        <ul className="space-y-1 text-sm">
                            {insights.demand.map((d) => (
                                <li key={d.product_id} className="flex items-center justify-between gap-2 border-b border-neutral-800/60 py-1.5 last:border-0">
                                    <Link href={`/admin/products/${d.product_id}/edit`} className="min-w-0 truncate text-neutral-200 hover:text-brand-gold" dir="auto">
                                        {loc(d.name_ar, d.name_en)}
                                    </Link>
                                    <span className="shrink-0 text-xs text-red-300">{t('admin.dashboard.insights.demandCount', { count: d.count })}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            {/* Customers & loyalty */}
            <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <MiniStat icon={Users} label={t('admin.dashboard.customers.total')} value={customers.total} href="/admin/customers" />
                <MiniStat icon={UserPlus} label={t('admin.dashboard.customers.new')} value={customers.new30} href="/admin/customers" />
                <MiniStat icon={MessageCircle} label={t('admin.dashboard.customers.whatsapp')} value={customers.whatsappAudience} href="/admin/marketing" />
                <MiniStat icon={Gift} label={t('admin.dashboard.customers.nearReward')} value={customers.nearReward} href="/admin/customers" highlight={customers.nearReward > 0} />
            </div>

            {/* Recent orders */}
            <div className="mt-6 overflow-hidden rounded-xl border border-neutral-800 bg-neutral-900">
                <div className="flex items-center justify-between border-b border-neutral-800 px-5 py-3">
                    <h2 className="flex items-center gap-2 font-semibold text-neutral-100"><ShoppingBag className="h-4 w-4 text-brand-gold" /> {t('admin.dashboard.recentOrders')}</h2>
                    <Link href="/admin/orders" className="text-sm text-brand-gold hover:underline">{t('admin.dashboard.viewAll')}</Link>
                </div>

                {recentOrders.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-neutral-500">{t('admin.dashboard.noRecentOrders')}</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-neutral-50 text-start text-neutral-600 dark:bg-neutral-800/50 dark:text-neutral-300">
                                <tr className="border-b border-neutral-800">
                                    <th className="px-5 py-3 text-start font-medium">{t('admin.dashboard.order')}</th>
                                    <th className="px-5 py-3 text-start font-medium">{t('admin.common.customer')}</th>
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
                                                {t(`status.${o.status}`)}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3 text-neutral-300" dir="ltr">{o.total.toFixed(2)} {sar}</td>
                                        <td className="px-5 py-3 text-neutral-400" dir="ltr">{o.created_at ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}

function MiniStat({ icon: Icon, label, value, href, highlight }: { icon: LucideIcon; label: string; value: number; href: string; highlight?: boolean }) {
    return (
        <Link
            href={href}
            className={`flex items-center gap-3 rounded-xl border p-4 transition-colors ${
                highlight ? 'border-brand-gold/40 bg-brand-gold/10 hover:border-brand-gold' : 'border-neutral-800 bg-neutral-900 hover:border-neutral-700'
            }`}
        >
            <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${highlight ? 'bg-brand-gold/20 text-brand-gold' : 'bg-neutral-800 text-neutral-300'}`}>
                <Icon className="h-5 w-5" />
            </span>
            <div className="min-w-0">
                <div className="text-xl font-bold text-neutral-100">{value.toLocaleString()}</div>
                <div className="truncate text-xs text-neutral-400">{label}</div>
            </div>
        </Link>
    );
}
