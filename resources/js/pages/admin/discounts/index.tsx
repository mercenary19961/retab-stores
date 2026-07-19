import { Head, router, useForm, usePage } from '@inertiajs/react';
import { BadgePercent, Percent, RotateCcw, Trash2, Truck, Upload } from 'lucide-react';
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
interface HistoryRow { id: number; mode: string; applied: number; discount_mode: string; value: number | null; user: string | null; created_at: string | null; reverted_at: string | null }
interface FreeShippingState { active: boolean; starts_at: string | null; ends_at: string | null; live: boolean }

const INPUT =
    'mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 focus:border-brand-gold focus:outline-none focus:ring-1 focus:ring-brand-gold dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100';
const CARD = 'rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900';
const STATUS_STYLE: Record<string, string> = {
    active: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    expired: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
};

// 'YYYY-MM-DD HH:MM:SS' → the value a <input type="datetime-local"> expects.
const toInput = (dt: string | null) => (dt ? dt.replace(' ', 'T').slice(0, 16) : '');

export default function DiscountsIndex({
    discounted,
    categories,
    activeCount,
    activeNow,
    freeShipping,
    history,
}: {
    discounted: DiscountedRow[];
    categories: CategoryOpt[];
    activeCount: number;
    activeNow: number;
    freeShipping: FreeShippingState;
    history: HistoryRow[];
}) {
    const { t, i18n } = useAdminT();
    const flash = (usePage().props as { flash?: { success?: string | null; error?: string | null } }).flash;
    const loc = (ar: string, en: string | null) => (i18n.language === 'en' && en ? en : ar);
    const sar = t('admin.common.sar');
    const money = (n: number) => `${Math.round(n).toLocaleString()} ${sar}`;
    const dateOnly = (dt: string | null) => (dt ? dt.slice(0, 10) : null);

    // Bulk apply form.
    const bulk = useForm({ mode: 'percentage', value: '', max_discount: '', category_id: '', starts_at: '', ends_at: '' });
    const isPct = bulk.data.mode === 'percentage';
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

    // Automatic free-shipping promotion.
    const free = useForm({ active: freeShipping.active, starts_at: toInput(freeShipping.starts_at), ends_at: toInput(freeShipping.ends_at) });
    const saveFree = (e: FormEvent) => {
        e.preventDefault();
        free.post('/admin/discounts/free-shipping', { preserveScroll: true });
    };
    const freeStatus = freeShipping.live ? 'active' : freeShipping.active ? 'scheduled' : 'off';

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

    const historyLabel = (h: HistoryRow) => {
        if (h.mode === 'import') return t('admin.discounts.history.mode_import', { n: h.applied });
        if (h.mode === 'clear') return t('admin.discounts.history.mode_clear', { n: h.applied });
        if (h.discount_mode === 'fixed') return t('admin.discounts.history.mode_bulk_fixed', { n: h.applied, value: h.value ?? 0, sar });
        return t('admin.discounts.history.mode_bulk_percent', { n: h.applied, value: h.value ?? 0 });
    };

    // ---- Cards, defined once and placed into the two columns below. ----

    const currentCard = (
        <section className={CARD}>
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
                <StickyScrollWrapper className="max-h-[26rem] overflow-y-auto">
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
    );

    const historyCard = (
        <section className={CARD}>
            <h2 className="mb-4 flex items-center gap-2 font-bold"><RotateCcw className="h-4 w-4 text-brand-gold" /> {t('admin.discounts.history.title')}</h2>
            {history.length === 0 ? (
                <p className="py-4 text-center text-sm text-neutral-400">{t('admin.discounts.history.empty')}</p>
            ) : (
                <ul className="max-h-80 space-y-2 overflow-y-auto text-sm">
                    {history.map((h) => (
                        <li key={h.id} className="flex flex-wrap items-center justify-between gap-2 rounded border border-neutral-100 px-3 py-2 dark:border-neutral-800">
                            <div>
                                <span className="font-medium text-neutral-700 dark:text-neutral-200">{historyLabel(h)}</span>
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
    );

    const bulkCard = (
        <section className={CARD}>
            <h2 className="mb-1 flex items-center gap-2 font-bold"><Percent className="h-4 w-4 text-brand-gold" /> {t('admin.discounts.bulk.title')}</h2>
            <p className="mb-4 text-sm text-neutral-500">{t('admin.discounts.bulk.desc')}</p>
            <form onSubmit={applyBulk} className="space-y-4">
                <div className="flex gap-2">
                    {(['percentage', 'fixed'] as const).map((m) => (
                        <button
                            key={m}
                            type="button"
                            onClick={() => bulk.setData('mode', m)}
                            className={`rounded-lg border px-3 py-1.5 text-sm transition-colors ${bulk.data.mode === m ? 'border-brand-gold bg-brand-gold/10 text-brand-gold' : 'border-neutral-300 text-neutral-500 dark:border-neutral-700'}`}
                        >
                            {t(`admin.discounts.bulk.mode_${m}`)}
                        </button>
                    ))}
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm">
                        <span className="text-neutral-600 dark:text-neutral-300">{isPct ? t('admin.discounts.bulk.percent') : t('admin.discounts.bulk.amount')}</span>
                        <input type="number" step={isPct ? '1' : '0.01'} min={isPct ? '1' : '0.01'} max={isPct ? '99' : undefined} value={bulk.data.value} onChange={(e) => bulk.setData('value', e.target.value)} className={INPUT} placeholder={isPct ? '20' : '10'} />
                        {bulk.errors.value && <span className="mt-1 block text-xs text-red-500">{bulk.errors.value}</span>}
                    </label>
                    {isPct && (
                        <label className="block text-sm">
                            <span className="text-neutral-600 dark:text-neutral-300">{t('admin.discounts.bulk.maxDiscount')}</span>
                            <input type="number" step="0.01" min="0" value={bulk.data.max_discount} onChange={(e) => bulk.setData('max_discount', e.target.value)} className={INPUT} placeholder={t('admin.discounts.bulk.optional')} />
                        </label>
                    )}
                </div>
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
                    <Button type="submit" variant="primary" icon={BadgePercent} disabled={bulk.processing || !bulk.data.value}>{t('admin.discounts.bulk.apply')}</Button>
                </div>
            </form>
        </section>
    );

    const importCard = (
        <section className={CARD}>
            <h2 className="mb-1 flex items-center gap-2 font-bold"><Upload className="h-4 w-4 text-brand-gold" /> {t('admin.discounts.import.title')}</h2>
            <p className="mb-4 text-sm text-neutral-500">{t('admin.discounts.import.desc')}</p>
            <form onSubmit={submitImport} className="space-y-4">
                <input
                    type="file"
                    accept=".csv,text/csv"
                    onChange={(e) => imp.setData('file', e.target.files?.[0] ?? null)}
                    className="block w-full text-sm text-neutral-500 file:me-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-brand-gold/15 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-gold file:transition-colors hover:file:bg-brand-gold/30"
                />
                {imp.errors.file && <span className="block text-xs text-red-500">{imp.errors.file}</span>}
                <div className="rounded-md border border-neutral-200 bg-neutral-50 p-3 font-mono text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-950">
                    sku, discount_percent<br />SK-1001, 20<br />SK-1002, 15
                </div>
                <Button type="submit" variant="secondary" icon={Upload} disabled={imp.processing || !imp.data.file}>{t('admin.discounts.import.upload')}</Button>
            </form>
        </section>
    );

    const freeCard = (
        <section className={CARD}>
            <div className="mb-1 flex items-center justify-between">
                <h2 className="flex items-center gap-2 font-bold"><Truck className="h-4 w-4 text-brand-gold" /> {t('admin.discounts.free.title')}</h2>
                <span className={`rounded-full px-2 py-0.5 text-xs ${freeStatus === 'off' ? 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300' : STATUS_STYLE[freeStatus === 'active' ? 'active' : 'scheduled']}`}>{t(`admin.discounts.free.status_${freeStatus}`)}</span>
            </div>
            <p className="mb-4 text-sm text-neutral-500">{t('admin.discounts.free.desc')}</p>
            <form onSubmit={saveFree} className="space-y-4">
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={free.data.active} onChange={(e) => free.setData('active', e.target.checked)} className="h-4 w-4 accent-brand-gold" />
                    {t('admin.discounts.free.enable')}
                </label>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm">
                        <span className="text-neutral-600 dark:text-neutral-300">{t('admin.discounts.bulk.startsAt')}</span>
                        <input type="datetime-local" value={free.data.starts_at} onChange={(e) => free.setData('starts_at', e.target.value)} className={INPUT} />
                    </label>
                    <label className="block text-sm">
                        <span className="text-neutral-600 dark:text-neutral-300">{t('admin.discounts.bulk.endsAt')}</span>
                        <input type="datetime-local" value={free.data.ends_at} onChange={(e) => free.setData('ends_at', e.target.value)} className={INPUT} />
                        {free.errors.ends_at && <span className="mt-1 block text-xs text-red-500">{free.errors.ends_at}</span>}
                    </label>
                </div>
                <div className="flex justify-end">
                    <Button type="submit" variant="primary" icon={Truck} disabled={free.processing}>{t('admin.discounts.free.save')}</Button>
                </div>
            </form>
        </section>
    );

    return (
        <AdminLayout title={t('admin.discounts.title')}>
            <Head title={t('admin.discounts.title')} />

            {flash?.success && <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{flash.success}</div>}
            {flash?.error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</div>}

            <p className="mb-4 text-sm text-neutral-400">{t('admin.discounts.subtitle')}</p>

            {/* Summary strip */}
            <div className="mb-6 flex flex-wrap gap-3 text-sm">
                <span className="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-neutral-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300">
                    {t('admin.discounts.summary.discounted', { active: activeNow, total: discounted.length })}
                </span>
                <span className={`rounded-lg border px-3 py-2 ${freeStatus === 'off' ? 'border-neutral-200 bg-white text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900' : STATUS_STYLE[freeStatus === 'active' ? 'active' : 'scheduled'] + ' border-transparent'}`}>
                    {t(`admin.discounts.summary.free_${freeStatus}`)}
                </span>
            </div>

            <div className="grid items-start gap-6 lg:grid-cols-2">
                {/* Left: what's live now + the audit trail */}
                <div className="space-y-6">
                    {currentCard}
                    {historyCard}
                </div>
                {/* Right: the tools that create discounts */}
                <div className="space-y-6">
                    {bulkCard}
                    {importCard}
                    {freeCard}
                </div>
            </div>
        </AdminLayout>
    );
}
