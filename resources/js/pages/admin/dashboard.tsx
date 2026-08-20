import StatusBadge from '@/components/admin/status-badge';
import StatusPill from '@/components/status-pill';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { THEAD } from '@/lib/admin-ui';
import { relativeTimeFromMinutes } from '@/lib/relative-time';
import { Head, Link } from '@inertiajs/react';
import {
    Banknote,
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
    drafts: number;
    lowStockList: LowStockRow[];
}
interface TopProduct {
    product_id: number;
    name_ar: string;
    name_en: string | null;
    qty: number;
    revenue: number | null;
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

export default function AdminDashboard({
    kpis,
    trend,
    tasks,
    inventory,
    insights,
    customers,
    recentOrders,
}: {
    kpis: Kpis | null;
    trend: TrendPoint[] | null;
    tasks: Task[];
    inventory: Inventory;
    insights: { topProducts: TopProduct[]; demand: DemandRow[] };
    customers: Customers;
    recentOrders?: RecentOrder[] | null;
}) {
    const { t, i18n } = useAdminT();
    const loc = (ar: string | null, en: string | null) => (i18n.language === 'en' && en ? en : (ar ?? '—'));
    const sar = t('admin.common.sar');
    const money = (n: number) => `${Math.round(n).toLocaleString()} ${sar}`;

    const delta = (cur: number, prev: number): { pct: number | null; up: boolean } => {
        if (prev <= 0) return { pct: cur > 0 ? 100 : null, up: cur > 0 };
        return { pct: ((cur - prev) / prev) * 100, up: cur >= prev };
    };

    // null = withheld from this viewer (money is admin-only), not zero.
    const aov = kpis && kpis.orders30 ? kpis.revenue30 / kpis.orders30 : 0;
    const revenueDelta = delta(kpis?.revenue30 ?? 0, kpis?.revenuePrev30 ?? 0);

    // Greeting + date come from the VIEWER's clock. Rendering them server-side
    // would show the container's hour and locale, which for a KSA client on a
    // Railway box is routinely the wrong half of the day.
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'morning' : hour < 18 ? 'afternoon' : 'evening';
    const today = new Date().toLocaleDateString(i18n.language, { weekday: 'long', day: 'numeric', month: 'long' });
    const trendMax = Math.max(1, ...(trend ?? []).map((p) => p.revenue));
    // 'YYYY-MM-DD' → localized short day (parse as local midnight to avoid TZ off-by-one).
    const fmtDay = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString(i18n.language, { month: 'short', day: 'numeric' });
    const openTasks = tasks.filter((task) => task.count > 0);
    const urgentCount = openTasks.filter((task) => task.urgent).length;
    // Bars scale against the best seller, so the leader is always full-width.
    const topQtyMax = Math.max(1, ...insights.topProducts.map((p) => p.qty));

    return (
        <AdminLayout title={t('admin.dashboard.title')}>
            <Head title={t('admin.dashboard.title')} />

            {/* Direction B opens by addressing the reader, not by labelling the page.
                The greeting is computed client-side from the viewer's own clock —
                a server-rendered one would show the container's hour. */}
            <div className="mb-6 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h1 className="font-heading text-xl font-bold text-neutral-100">{t(`admin.dashboard.greeting.${greeting}`)}</h1>
                <span className="text-sm text-neutral-500">{today}</span>
            </div>

            {/* THE HERO — direction B. One number dominates, in gold on brand teal,
                with the chart and the supporting figures inside the same panel rather
                than as three competing cards. Admin-only: `kpis` is null for editors
                (money is admins only), and the whole block goes with it. */}
            {kpis && (
                <section className="from-brand-teal overflow-hidden rounded-2xl bg-gradient-to-br to-[#14383c] p-6 sm:p-7">
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:gap-9">
                        {/* Headline figure */}
                        <div className="flex shrink-0 flex-col gap-1.5 lg:w-64">
                            <span className="text-xs tracking-wide text-[#9fc0c2]">{t('admin.dashboard.kpis.revenue30')}</span>
                            <div className="flex flex-wrap items-baseline gap-x-2.5">
                                {/* dir=ltr: a large figure must not be reordered under RTL. */}
                                <span className="font-heading text-4xl leading-none font-bold tracking-tight text-[#d9bc82] sm:text-5xl" dir="ltr">
                                    {Math.round(kpis.revenue30).toLocaleString()}
                                </span>
                                <span className="text-base text-[#9fc0c2]">{sar}</span>
                            </div>
                            {revenueDelta.pct !== null && (
                                <div className="mt-1 flex items-center gap-1.5 text-xs">
                                    <span
                                        className={`inline-flex items-center gap-1 font-semibold ${revenueDelta.up ? 'text-[#7fd1a0]' : 'text-red-300'}`}
                                    >
                                        {revenueDelta.up ? <TrendingUp className="h-3.5 w-3.5" /> : <TrendingDown className="h-3.5 w-3.5" />}
                                        {Math.abs(revenueDelta.pct).toFixed(0)}%
                                    </span>
                                    <span className="text-[#7f9fa1]">{t('admin.dashboard.kpis.vsPrev')}</span>
                                </div>
                            )}
                        </div>

                        {/* Chart, sharing the hero rather than owning a card of its own. */}
                        {trend && (
                            <div className="min-w-0 flex-grow">
                                {trendMax <= 1 ? (
                                    <p className="py-8 text-center text-sm text-[#7f9fa1]">{t('admin.dashboard.trend.empty')}</p>
                                ) : (
                                    <>
                                        <div className="flex h-24 items-end gap-1 border-b border-[#2c5f64] pb-px" dir="ltr">
                                            {trend.map((p, i) => (
                                                <div key={p.date} className="group relative flex h-full flex-1 flex-col justify-end">
                                                    <div
                                                        className={`w-full rounded-t transition-colors ${
                                                            i >= trend.length - 3 ? 'bg-brand-gold' : 'bg-[#2c5f64] group-hover:bg-[#3d7a80]'
                                                        }`}
                                                        style={{ height: `${Math.max(2, (p.revenue / trendMax) * 100)}%` }}
                                                    />
                                                    <div className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 rounded-md border border-[#2c5f64] bg-[#0f2f33] px-2 py-1 text-start whitespace-nowrap shadow-lg group-hover:block">
                                                        <div className="text-xs font-medium text-neutral-100">{fmtDay(p.date)}</div>
                                                        <div className="text-brand-gold text-xs">{money(p.revenue)}</div>
                                                        <div className="text-[11px] text-[#9fc0c2]">
                                                            {t('admin.dashboard.trend.orders', { n: p.orders })}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                        <div className="mt-2 flex justify-between text-[11px] text-[#7f9fa1]" dir="ltr">
                                            <span>{fmtDay(trend[0].date)}</span>
                                            <span>{fmtDay(trend[trend.length - 1].date)}</span>
                                        </div>
                                    </>
                                )}
                            </div>
                        )}

                        {/* The other three figures, demoted to a supporting column — the
                            point of B is that they are not peers of the headline. */}
                        <div className="grid shrink-0 grid-cols-3 gap-4 border-t border-[#2c5f64] pt-4 lg:grid-cols-1 lg:gap-3.5 lg:border-s lg:border-t-0 lg:ps-7 lg:pt-0">
                            <div className="flex flex-col">
                                <span className="text-[11px] text-[#7f9fa1]">{t('admin.dashboard.kpis.orders30')}</span>
                                <span className="text-lg font-bold tracking-tight text-neutral-100">{kpis.orders30.toLocaleString()}</span>
                            </div>
                            <div className="flex flex-col">
                                <span className="text-[11px] text-[#7f9fa1]">{t('admin.dashboard.kpis.aov')}</span>
                                <span className="text-lg font-bold tracking-tight text-neutral-100">{money(aov)}</span>
                            </div>
                            <div className="flex flex-col">
                                <span className="text-[11px] text-[#7f9fa1]">{t('admin.dashboard.kpis.revenueToday')}</span>
                                <span className="text-lg font-bold tracking-tight text-neutral-100">{money(kpis.revenueToday)}</span>
                            </div>
                        </div>
                    </div>
                </section>
            )}

            {/* Needs attention.
                ⚠️ Two different empty states, and conflating them misleads. `tasks`
                empty means the viewer has permission for NONE of these sections, so
                the panel is hidden outright — telling a catalogue editor "all clear"
                would claim there is no outstanding work when we simply never looked.
                `openTasks` empty with tiles present is the real all-clear. */}
            {tasks.length > 0 && (
                <div className="mt-6 rounded-xl border border-neutral-800 bg-neutral-900 p-5">
                    <h2 className="mb-4 flex flex-wrap items-baseline gap-x-3 gap-y-1 font-semibold text-neutral-100">
                        <span className="flex items-center gap-2">
                            <BellRing className="text-brand-gold h-4 w-4" /> {t('admin.dashboard.tasks.title')}
                        </span>
                        {urgentCount > 0 && (
                            <span className="text-xs font-normal text-amber-400/90">
                                {t('admin.dashboard.tasks.urgentCount', { n: urgentCount })}
                            </span>
                        )}
                    </h2>
                    {openTasks.length === 0 ? (
                        <p className="text-sm text-neutral-500">{t('admin.dashboard.tasks.allClear')}</p>
                    ) : (
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                            {openTasks.map((task) => {
                                const Icon = TASK_ICON[task.key] ?? ShoppingBag;
                                return (
                                    <Link
                                        key={task.key}
                                        href={task.href}
                                        className={`flex min-h-28 flex-col gap-2 rounded-xl border p-4 transition-colors ${
                                            task.urgent
                                                ? 'border-amber-500/25 bg-amber-500/[0.06] hover:border-amber-500/60'
                                                : 'border-neutral-800 bg-neutral-950/40 hover:border-neutral-700'
                                        }`}
                                    >
                                        {/* The urgency word does the work the colour used to.
                                            Only the money-losing queues are tinted, which is
                                            what stops the tint becoming wallpaper. */}
                                        <span className="flex items-center gap-1.5">
                                            <span
                                                className={`h-1.5 w-1.5 shrink-0 rounded-full ${task.urgent ? 'bg-amber-500' : 'bg-brand-gold/60'}`}
                                            />
                                            <span
                                                className={`truncate text-[10px] font-semibold tracking-wider ${task.urgent ? 'text-amber-400/90' : 'text-neutral-500'}`}
                                            >
                                                {t(task.urgent ? 'admin.dashboard.tasks.timeSensitive' : 'admin.dashboard.tasks.today')}
                                            </span>
                                        </span>
                                        <span
                                            className={`font-heading text-3xl leading-none font-bold tracking-tight ${task.urgent ? 'text-amber-300' : 'text-neutral-100'}`}
                                        >
                                            {task.count}
                                        </span>
                                        <span className="flex items-start gap-1.5 text-xs leading-snug text-neutral-400">
                                            <Icon className="mt-px h-3.5 w-3.5 shrink-0 opacity-60" />
                                            <span className="min-w-0">{t(`admin.dashboard.tasks.${task.key}`)}</span>
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}

            {/* Inventory health + Insights */}
            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Inventory */}
                <section className="rounded-xl border border-neutral-800 bg-neutral-900 p-5">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="flex items-center gap-2 font-semibold text-neutral-100">
                            <Boxes className="text-brand-gold h-4 w-4" /> {t('admin.dashboard.inventory.title')}
                        </h2>
                        <Link href="/admin/stock-import" className="text-brand-gold text-xs hover:underline">
                            {t('admin.dashboard.viewAll')}
                        </Link>
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
                              ? t('admin.dashboard.inventory.syncStale', {
                                    ago: relativeTimeFromMinutes(inventory.lastSynced.minutes, i18n.language),
                                })
                              : t('admin.dashboard.inventory.syncOk', { ago: relativeTimeFromMinutes(inventory.lastSynced.minutes, i18n.language) })}
                    </div>

                    {/* Direction B: a coloured leading rule instead of four boxes.
                        The rule carries the severity, so out-of-stock reads as urgent
                        without the number having to shout. `border-s` not `border-l`,
                        so it flips with the reading direction. */}
                    <div className="mb-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4">
                        <div className="flex flex-col gap-0.5 border-s-2 border-red-400/70 ps-3">
                            <span className={`text-xl font-bold ${inventory.outOfStock ? 'text-red-400' : 'text-neutral-100'}`}>
                                {inventory.outOfStock}
                            </span>
                            <span className="text-[11px] text-neutral-500">{t('admin.dashboard.inventory.outOfStock')}</span>
                        </div>
                        <div className="flex flex-col gap-0.5 border-s-2 border-amber-400/70 ps-3">
                            <span className={`text-xl font-bold ${inventory.lowStock ? 'text-amber-400' : 'text-neutral-100'}`}>
                                {inventory.lowStock}
                            </span>
                            <span className="text-[11px] text-neutral-500">{t('admin.dashboard.inventory.lowStock')}</span>
                        </div>
                        <div className="flex flex-col gap-0.5 border-s-2 border-neutral-700 ps-3">
                            <span className="text-xl font-bold text-neutral-100">{inventory.activeProducts}</span>
                            <span className="text-[11px] text-neutral-500">{t('admin.dashboard.inventory.active')}</span>
                        </div>
                        {/* Moved here from the action queue: a backlog that never
                            reaches zero belongs with the stock metrics, not beside
                            decisions due today. Still one click from the drafts list. */}
                        <Link href="/admin/products?status=draft" className="group flex flex-col gap-0.5 border-s-2 border-neutral-700 ps-3">
                            <span className="group-hover:text-brand-gold text-xl font-bold text-neutral-100 transition-colors">
                                {inventory.drafts}
                            </span>
                            <span className="text-[11px] text-neutral-500">{t('admin.dashboard.inventory.drafts')}</span>
                        </Link>
                    </div>

                    <h3 className="mb-2 text-xs font-semibold tracking-wide text-neutral-500 uppercase">
                        {t('admin.dashboard.inventory.lowStockList')}
                    </h3>
                    {inventory.lowStockList.length === 0 ? (
                        <p className="text-sm text-neutral-500">{t('admin.dashboard.inventory.allStocked')}</p>
                    ) : (
                        <ul className="space-y-1 text-sm">
                            {inventory.lowStockList.map((p) => (
                                <li
                                    key={p.id}
                                    className="flex items-center justify-between gap-2 border-b border-neutral-800/60 py-1.5 last:border-0"
                                >
                                    <Link
                                        href={`/admin/products/${p.id}/edit`}
                                        className="hover:text-brand-gold min-w-0 truncate text-neutral-200"
                                        dir="auto"
                                    >
                                        {loc(p.name_ar, p.name_en)}
                                    </Link>
                                    <StatusPill tone={p.stock <= 0 ? 'stopped' : 'attention'} className="shrink-0">
                                        {t('admin.dashboard.inventory.units', { count: p.stock })}
                                    </StatusPill>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {/* Insights */}
                <section className="rounded-xl border border-neutral-800 bg-neutral-900 p-5">
                    <h2 className="mb-3 flex items-center gap-2 font-semibold text-neutral-100">
                        <Trophy className="text-brand-gold h-4 w-4" /> {t('admin.dashboard.insights.topProducts')}
                    </h2>
                    {/* Direction B: a bar per product, scaled against the best seller,
                        so the ranking reads at a glance instead of needing the numbers
                        compared by eye. (A JSX comment cannot sit in a ternary branch
                        alongside the element — it has to live out here.) */}
                    {insights.topProducts.length === 0 ? (
                        <p className="mb-5 text-sm text-neutral-500">{t('admin.dashboard.insights.noSales')}</p>
                    ) : (
                        <ul className="mb-5 space-y-2.5">
                            {insights.topProducts.map((p) => (
                                <li key={p.product_id} className="flex flex-col gap-1.5">
                                    <div className="flex items-baseline justify-between gap-2 text-sm">
                                        <Link
                                            href={`/admin/products/${p.product_id}/edit`}
                                            className="hover:text-brand-gold min-w-0 truncate text-neutral-200"
                                            dir="auto"
                                        >
                                            {loc(p.name_ar, p.name_en)}
                                        </Link>
                                        <span className="shrink-0 text-xs text-neutral-400">
                                            {t('admin.dashboard.insights.sold', { count: p.qty })}
                                            {p.revenue !== null && <> · {money(p.revenue)}</>}
                                        </span>
                                    </div>
                                    <div className="h-1 overflow-hidden rounded-full bg-neutral-800">
                                        <div
                                            className="bg-brand-gold h-full rounded-full"
                                            style={{ width: `${Math.max(4, (p.qty / topQtyMax) * 100)}%` }}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}

                    <h3 className="mb-2 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-neutral-500 uppercase">
                        <PackageX className="h-3.5 w-3.5" /> {t('admin.dashboard.insights.demand')}
                    </h3>
                    {insights.demand.length === 0 ? (
                        <p className="text-sm text-neutral-500">{t('admin.dashboard.insights.noDemand')}</p>
                    ) : (
                        <ul className="space-y-1 text-sm">
                            {insights.demand.map((d) => (
                                <li
                                    key={d.product_id}
                                    className="flex items-center justify-between gap-2 border-b border-neutral-800/60 py-1.5 last:border-0"
                                >
                                    <Link
                                        href={`/admin/products/${d.product_id}/edit`}
                                        className="hover:text-brand-gold min-w-0 truncate text-neutral-200"
                                        dir="auto"
                                    >
                                        {loc(d.name_ar, d.name_en)}
                                    </Link>
                                    <span className="shrink-0 text-xs text-red-300">
                                        {t('admin.dashboard.insights.demandCount', { count: d.count })}
                                    </span>
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
                <MiniStat
                    icon={MessageCircle}
                    label={t('admin.dashboard.customers.whatsapp')}
                    value={customers.whatsappAudience}
                    href="/admin/marketing"
                />
                <MiniStat
                    icon={Gift}
                    label={t('admin.dashboard.customers.nearReward')}
                    value={customers.nearReward}
                    href="/admin/customers"
                    highlight={customers.nearReward > 0}
                />
            </div>

            {/* Recent orders. Hidden entirely without `orders.view` — every row links
                to an order page that would 403, and rendering "no orders yet" instead
                would state something false. null = withheld, [] = genuinely none. */}
            {recentOrders !== null && recentOrders !== undefined && (
                <div className="mt-6 overflow-hidden rounded-xl border border-neutral-800 bg-neutral-900">
                    <div className="flex items-center justify-between border-b border-neutral-800 px-5 py-3">
                        <h2 className="flex items-center gap-2 font-semibold text-neutral-100">
                            <ShoppingBag className="text-brand-gold h-4 w-4" /> {t('admin.dashboard.recentOrders')}
                        </h2>
                        <Link href="/admin/orders" className="text-brand-gold text-sm hover:underline">
                            {t('admin.dashboard.viewAll')}
                        </Link>
                    </div>

                    {recentOrders.length === 0 ? (
                        <p className="px-5 py-8 text-center text-sm text-neutral-500">{t('admin.dashboard.noRecentOrders')}</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className={THEAD}>
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
                                                <Link
                                                    href={`/admin/orders/${o.order_number}`}
                                                    className="text-brand-gold font-semibold hover:underline"
                                                >
                                                    {o.order_number}
                                                </Link>
                                            </td>
                                            <td className="px-5 py-3 text-neutral-300" dir="auto">
                                                {o.customer_name}
                                            </td>
                                            <td className="px-5 py-3">
                                                <StatusBadge domain="order" value={o.status} />
                                            </td>
                                            <td className="px-5 py-3 text-neutral-300" dir="ltr">
                                                {o.total.toFixed(2)} {sar}
                                            </td>
                                            <td className="px-5 py-3 text-neutral-400" dir="ltr">
                                                {o.created_at ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}
        </AdminLayout>
    );
}

function MiniStat({
    icon: Icon,
    label,
    value,
    href,
    highlight,
}: {
    icon: LucideIcon;
    label: string;
    value: number;
    href: string;
    highlight?: boolean;
}) {
    return (
        <Link
            href={href}
            className={`flex items-center gap-3 rounded-xl border p-4 transition-colors ${
                highlight
                    ? 'border-brand-gold/40 bg-brand-gold/10 hover:border-brand-gold'
                    : 'border-neutral-800 bg-neutral-900 hover:border-neutral-700'
            }`}
        >
            <span
                className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${highlight ? 'bg-brand-gold/20 text-brand-gold' : 'bg-neutral-800 text-neutral-300'}`}
            >
                <Icon className="h-5 w-5" />
            </span>
            <div className="min-w-0">
                <div className="text-xl font-bold text-neutral-100">{value.toLocaleString()}</div>
                <div className="truncate text-xs text-neutral-400">{label}</div>
            </div>
        </Link>
    );
}
