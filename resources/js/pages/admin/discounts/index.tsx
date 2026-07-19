import { Head, router, useForm, usePage } from '@inertiajs/react';
import { BadgePercent, Percent, RotateCcw, Trash2, Upload } from 'lucide-react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import ConfirmDeleteButton from '@/components/admin/confirm-delete-button';
import Select from '@/components/admin/select';
import StickyScrollWrapper from '@/components/admin/sticky-scroll-wrapper';
import { useAdminT } from '@/i18n/use-admin-t';

interface DiscountedRow {
    id: number;
    name_ar: string;
    name_en: string | null;
    sku: string;
    price: number;
    sale_price: number;
    percent: number;
    starts_at: string | null;
    ends_at: string | null;
    status: string;
}
interface CategoryOpt { id: number; name_ar: string; name_en: string | null; count: number }
interface HistoryRow { id: number; mode: string; applied: number; percent: number | null; user: string | null; created_at: string | null; reverted_at: string | null }

const INPUT =
    'mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 focus:border-brand-gold focus:outline-none focus:ring-1 focus:ring-brand-gold dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100';
const CARD = 'rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900';
const STATUS_STYLE: Record<string, string> = {
    active: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    expired: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
};

export default function DiscountsIndex({
    discounted,
    categories,
    activeCount,
    history,
}: {
    discounted: DiscountedRow[];
    categories: CategoryOpt[];
    activeCount: number;
    history: HistoryRow[];
}) {
    const { t, i18n } = useAdminT();
    const flash = (usePage().props as { flash?: { success?: string | null; error?: string | null } }).flash;
    const loc = (ar: string, en: string | null) => (i18n.language === 'en' && en ? en : ar);
    const sar = t('admin.common.sar');
    const money = (n: number) => `${Math.round(n).toLocaleString()} ${sar}`;
    const dateOnly = (dt: string | null) => (dt ? dt.slice(0, 10) : null);

    // Bulk apply form.
    const bulk = useForm({ percent: '', category_id: '', starts_at: '', ends_at: '' });
    const scopeCount = bulk.data.category_id
        ? (categories.find((c) => String(c.id) === bulk.data.category_id)?.count ?? 0)
        : activeCount;

    const applyBulk = (e: FormEvent) => {
        e.preventDefault();
        bulk.post('/admin/discounts/apply', { preserveScroll: true, onSuccess: () => bulk.reset() });
    };

    // CSV import → navigates to the preview page.
    const imp = useForm<{ file: File | null }>({ file: null });
    const submitImport = (e: FormEvent) => {
        e.preventDefault();
        if (!imp.data.file) return;
        imp.post('/admin/discounts/import/preview', { forceFormData: true });
    };

    const clearOne = (id: number) => router.post('/admin/discounts/clear', { product_id: id }, { preserveScroll: true });
    const undo = (id: number) => router.post(`/admin/discounts/undo/${id}`, {}, { preserveScroll: true });

    const windowLabel = (s: string | null, e: string | null) => {
        const a = dateOnly(s);
        const b = dateOnly(e);
        if (a && b) return `${a} → ${b}`;
        if (b) return t('admin.discounts.windowUntil', { date: b });
        if (a) return t('admin.discounts.windowFrom', { date: a });
        return t('admin.discounts.windowNone');
    };

    return (
        <AdminLayout title={t('admin.discounts.title')}>
            <Head title={t('admin.discounts.title')} />

            {flash?.success && <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{flash.success}</div>}
            {flash?.error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</div>}

            <p className="mb-6 text-sm text-neutral-400">{t('admin.discounts.subtitle')}</p>

            <div className="grid gap-6 lg:grid-cols-2">
                {/* Bulk apply */}
                <section className={CARD}>
                    <h2 className="mb-1 flex items-center gap-2 font-bold"><Percent className="h-4 w-4 text-brand-gold" /> {t('admin.discounts.bulk.title')}</h2>
                    <p className="mb-4 text-sm text-neutral-500">{t('admin.discounts.bulk.desc')}</p>
                    <form onSubmit={applyBulk} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="block text-sm">
                                <span className="text-neutral-600 dark:text-neutral-300">{t('admin.discounts.bulk.percent')}</span>
                                <input type="number" step="1" min="1" max="99" value={bulk.data.percent} onChange={(e) => bulk.setData('percent', e.target.value)} className={INPUT} placeholder="20" />
                                {bulk.errors.percent && <span className="mt-1 block text-xs text-red-500">{bulk.errors.percent}</span>}
                            </label>
                            <label className="block text-sm">
                                <span className="text-neutral-600 dark:text-neutral-300">{t('admin.discounts.bulk.scope')}</span>
                                <Select
                                    value={bulk.data.category_id}
                                    onChange={(v) => bulk.setData('category_id', v)}
                                    options={[
                                        { value: '', label: t('admin.discounts.bulk.scopeAll', { n: activeCount }) },
                                        ...categories.map((c) => ({ value: String(c.id), label: `${loc(c.name_ar, c.name_en)} (${c.count})` })),
                                    ]}
                                    className="mt-1 w-full"
                                />
                            </label>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="block text-sm">
                                <span className="text-neutral-600 dark:text-neutral-300">{t('admin.discounts.bulk.startsAt')}</span>
                                <input type="datetime-local" value={bulk.data.starts_at} onChange={(e) => bulk.setData('starts_at', e.target.value)} className={INPUT} />
                            </label>
                            <label className="block text-sm">
                                <span className="text-neutral-600 dark:text-neutral-300">{t('admin.discounts.bulk.endsAt')}</span>
                                <input type="datetime-local" value={bulk.data.ends_at} onChange={(e) => bulk.setData('ends_at', e.target.value)} className={INPUT} />
                                {bulk.errors.ends_at && <span className="mt-1 block text-xs text-red-500">{bulk.errors.ends_at}</span>}
                            </label>
                        </div>
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-xs text-neutral-400">{t('admin.discounts.bulk.appliesTo', { n: scopeCount })}</span>
                            <Button type="submit" variant="primary" icon={BadgePercent} disabled={bulk.processing || !bulk.data.percent}>{t('admin.discounts.bulk.apply')}</Button>
                        </div>
                    </form>
                </section>

                {/* CSV import */}
                <section className={CARD}>
                    <h2 className="mb-1 flex items-center gap-2 font-bold"><Upload className="h-4 w-4 text-brand-gold" /> {t('admin.discounts.import.title')}</h2>
                    <p className="mb-4 text-sm text-neutral-500">{t('admin.discounts.import.desc')}</p>
                    <form onSubmit={submitImport} className="space-y-4">
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(e) => imp.setData('file', e.target.files?.[0] ?? null)}
                            className="block w-full text-sm text-neutral-500 file:me-3 file:rounded-lg file:border-0 file:bg-brand-gold/15 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-gold"
                        />
                        {imp.errors.file && <span className="block text-xs text-red-500">{imp.errors.file}</span>}
                        <div className="rounded-md border border-neutral-200 bg-neutral-50 p-3 font-mono text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-950">
                            sku, discount_percent<br />SK-1001, 20<br />SK-1002, 15
                        </div>
                        <Button type="submit" variant="secondary" icon={Upload} disabled={imp.processing || !imp.data.file}>{t('admin.discounts.import.upload')}</Button>
                    </form>
                </section>
            </div>

            {/* Current discounts */}
            <section className={`${CARD} mt-6`}>
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="flex items-center gap-2 font-bold"><BadgePercent className="h-4 w-4 text-brand-gold" /> {t('admin.discounts.current.title')} ({discounted.length})</h2>
                    {discounted.length > 0 && (
                        <ConfirmDeleteButton
                            label={t('admin.discounts.current.clearAll')}
                            itemName={t('admin.discounts.current.clearAllItem', { n: discounted.length })}
                            reversible={false}
                            onConfirm={() => router.post('/admin/discounts/clear', {}, { preserveScroll: true })}
                        />
                    )}
                </div>
                {discounted.length === 0 ? (
                    <p className="py-6 text-center text-sm text-neutral-400">{t('admin.discounts.current.empty')}</p>
                ) : (
                    <StickyScrollWrapper>
                        <table className="min-w-full text-sm">
                            <thead className="border-b border-neutral-200 text-left text-neutral-600 dark:border-neutral-800 dark:text-neutral-300">
                                <tr>
                                    <th className="px-3 py-2">{t('admin.discounts.current.cols.product')}</th>
                                    <th className="px-3 py-2">{t('admin.discounts.current.cols.price')}</th>
                                    <th className="px-3 py-2">{t('admin.discounts.current.cols.window')}</th>
                                    <th className="px-3 py-2">{t('admin.discounts.current.cols.status')}</th>
                                    <th className="px-3 py-2 text-end">{t('admin.common.actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {discounted.map((p) => (
                                    <tr key={p.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                        <td className="px-3 py-2">
                                            <span dir="auto" className="font-medium text-neutral-800 dark:text-neutral-100">{loc(p.name_ar, p.name_en)}</span>
                                            <span className="ms-2 font-mono text-xs text-neutral-400">{p.sku}</span>
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-2">
                                            <span className="text-neutral-400 line-through">{money(p.price)}</span>{' '}
                                            <span className="font-medium text-neutral-800 dark:text-neutral-100">{money(p.sale_price)}</span>{' '}
                                            <span className="text-xs text-green-600 dark:text-green-400">-{p.percent}%</span>
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-2 text-neutral-500">{windowLabel(p.starts_at, p.ends_at)}</td>
                                        <td className="px-3 py-2"><span className={`rounded-full px-2 py-0.5 text-xs ${STATUS_STYLE[p.status] ?? ''}`}>{t(`admin.discounts.status.${p.status}`)}</span></td>
                                        <td className="px-3 py-2 text-end">
                                            <Button size="sm" variant="ghost" icon={Trash2} onClick={() => clearOne(p.id)}>{t('admin.discounts.current.clear')}</Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </StickyScrollWrapper>
                )}
            </section>

            {/* History */}
            <section className={`${CARD} mt-6`}>
                <h2 className="mb-4 flex items-center gap-2 font-bold"><RotateCcw className="h-4 w-4 text-brand-gold" /> {t('admin.discounts.history.title')}</h2>
                {history.length === 0 ? (
                    <p className="py-4 text-center text-sm text-neutral-400">{t('admin.discounts.history.empty')}</p>
                ) : (
                    <ul className="space-y-2 text-sm">
                        {history.map((h) => (
                            <li key={h.id} className="flex flex-wrap items-center justify-between gap-2 rounded border border-neutral-100 px-3 py-2 dark:border-neutral-800">
                                <div>
                                    <span className="font-medium text-neutral-700 dark:text-neutral-200">{t(`admin.discounts.history.mode_${h.mode}`, { n: h.applied, percent: h.percent ?? 0 })}</span>
                                    <span className="ms-2 text-xs text-neutral-400">{h.created_at}{h.user ? ` · ${h.user}` : ''}</span>
                                </div>
                                {h.reverted_at ? (
                                    <span className="text-xs text-neutral-400">{t('admin.discounts.history.reverted')}</span>
                                ) : (
                                    <Button size="sm" variant="secondary" icon={RotateCcw} onClick={() => undo(h.id)}>{t('admin.discounts.history.undo')}</Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AdminLayout>
    );
}
