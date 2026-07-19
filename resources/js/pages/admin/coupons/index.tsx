import { Head, router, useForm } from '@inertiajs/react';
import { Columns3, MoveHorizontal, Pencil, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import ConfirmDeleteButton from '@/components/admin/confirm-delete-button';
import Modal from '@/components/admin/modal';
import Pagination, { type Paginator } from '@/components/admin/pagination';
import ResizableTh from '@/components/admin/resizable-th';
import Select from '@/components/admin/select';
import StickyScrollWrapper from '@/components/admin/sticky-scroll-wrapper';
import { useResizableColumns, type ColumnDef } from '@/hooks/use-resizable-columns';
import { useAdminT } from '@/i18n/use-admin-t';

const COLUMNS: ColumnDef[] = [
    { key: 'code', defaultWidth: 170, minWidth: 110 },
    { key: 'discount', defaultWidth: 180, minWidth: 120 },
    { key: 'window', defaultWidth: 200, minWidth: 130 },
    { key: 'usage', defaultWidth: 160, minWidth: 110 },
    { key: 'status', defaultWidth: 120, minWidth: 90 },
    { key: 'actions', defaultWidth: 170, minWidth: 130 },
];

interface CouponRow {
    id: number;
    code: string;
    type: string;
    value: number;
    max_discount: number | null;
    min_order_total: number | null;
    usage_limit: number | null;
    used_count: number;
    per_user_limit: number | null;
    starts_at: string | null;
    expires_at: string | null;
    is_active: boolean;
    status: string;
    description_ar: string | null;
    description_en: string | null;
}

const INPUT =
    'mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 focus:border-brand-gold focus:outline-none focus:ring-1 focus:ring-brand-gold dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100';

const STATUS_STYLE: Record<string, string> = {
    active: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    expired: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    used_up: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    inactive: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300',
};

// 'YYYY-MM-DD HH:MM:SS' → the value a <input type="datetime-local"> expects.
const toInput = (dt: string | null) => (dt ? dt.replace(' ', 'T').slice(0, 16) : '');

// Modal body: create (coupon = null) or edit an existing coupon in place.
function CouponForm({ coupon, onClose }: { coupon: CouponRow | null; onClose: () => void }) {
    const { t } = useAdminT();
    const { data, setData, post, put, processing, errors, isDirty } = useForm({
        code: coupon?.code ?? '',
        type: coupon?.type ?? 'percentage',
        value: coupon?.value != null && coupon.type !== 'free_shipping' ? String(coupon.value) : '',
        max_discount: coupon?.max_discount != null ? String(coupon.max_discount) : '',
        min_order_total: coupon?.min_order_total != null ? String(coupon.min_order_total) : '',
        usage_limit: coupon?.usage_limit != null ? String(coupon.usage_limit) : '',
        per_user_limit: coupon?.per_user_limit != null ? String(coupon.per_user_limit) : '',
        starts_at: toInput(coupon?.starts_at ?? null),
        expires_at: toInput(coupon?.expires_at ?? null),
        is_active: coupon?.is_active ?? true,
        description_ar: coupon?.description_ar ?? '',
        description_en: coupon?.description_en ?? '',
    });

    const isPercentage = data.type === 'percentage';
    const isFreeShipping = data.type === 'free_shipping';

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, preserveState: true, onSuccess: () => onClose() };
        if (coupon) put(`/admin/coupons/${coupon.id}`, opts);
        else post('/admin/coupons', opts);
    };

    const err = (msg?: string) => msg && <span className="mt-1 block text-xs text-red-500">{msg}</span>;
    const lbl = (s: string) => <span className="text-sm font-medium text-neutral-600 dark:text-neutral-300">{s}</span>;

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <label className="block">
                    {lbl(t('admin.coupons.form.code'))}
                    <input
                        autoFocus
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.toUpperCase())}
                        placeholder="RAMADAN15"
                        className={`${INPUT} font-mono uppercase`}
                    />
                    <span className="mt-1 block text-xs text-neutral-400">{t('admin.coupons.form.codeHint')}</span>
                    {err(errors.code)}
                </label>
                <label className="block">
                    {lbl(t('admin.coupons.form.type'))}
                    <Select
                        value={data.type}
                        onChange={(v) => setData('type', v)}
                        options={[
                            { value: 'percentage', label: t('admin.coupons.types.percentage') },
                            { value: 'fixed', label: t('admin.coupons.types.fixed') },
                            { value: 'free_shipping', label: t('admin.coupons.types.free_shipping') },
                        ]}
                        className="mt-1 w-full"
                    />
                    {err(errors.type)}
                </label>
            </div>

            {!isFreeShipping && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block">
                        {lbl(isPercentage ? t('admin.coupons.form.valuePercent') : t('admin.coupons.form.valueFixed'))}
                        <input type="number" step="0.01" min="0" max={isPercentage ? 100 : undefined} value={data.value} onChange={(e) => setData('value', e.target.value)} className={INPUT} />
                        {err(errors.value)}
                    </label>
                    {isPercentage && (
                        <label className="block">
                            {lbl(t('admin.coupons.form.maxDiscount'))}
                            <input type="number" step="0.01" min="0" value={data.max_discount} onChange={(e) => setData('max_discount', e.target.value)} className={INPUT} placeholder={t('admin.coupons.form.optional')} />
                            <span className="mt-1 block text-xs text-neutral-400">{t('admin.coupons.form.maxDiscountHint')}</span>
                            {err(errors.max_discount)}
                        </label>
                    )}
                </div>
            )}

            <label className="block">
                {lbl(t('admin.coupons.form.minOrder'))}
                <input type="number" step="0.01" min="0" value={data.min_order_total} onChange={(e) => setData('min_order_total', e.target.value)} className={INPUT} placeholder={t('admin.coupons.form.optional')} />
                <span className="mt-1 block text-xs text-neutral-400">{t('admin.coupons.form.minOrderHint')}</span>
                {err(errors.min_order_total)}
            </label>

            <div className="grid gap-4 sm:grid-cols-2">
                <label className="block">
                    {lbl(t('admin.coupons.form.usageLimit'))}
                    <input type="number" step="1" min="1" value={data.usage_limit} onChange={(e) => setData('usage_limit', e.target.value)} className={INPUT} placeholder={t('admin.coupons.form.unlimited')} />
                    <span className="mt-1 block text-xs text-neutral-400">{t('admin.coupons.form.usageLimitHint')}</span>
                    {err(errors.usage_limit)}
                </label>
                <label className="block">
                    {lbl(t('admin.coupons.form.perUserLimit'))}
                    <input type="number" step="1" min="1" value={data.per_user_limit} onChange={(e) => setData('per_user_limit', e.target.value)} className={INPUT} placeholder={t('admin.coupons.form.unlimited')} />
                    <span className="mt-1 block text-xs text-neutral-400">{t('admin.coupons.form.perUserLimitHint')}</span>
                    {err(errors.per_user_limit)}
                </label>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <label className="block">
                    {lbl(t('admin.coupons.form.startsAt'))}
                    <input type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} className={INPUT} />
                    {err(errors.starts_at)}
                </label>
                <label className="block">
                    {lbl(t('admin.coupons.form.expiresAt'))}
                    <input type="datetime-local" value={data.expires_at} onChange={(e) => setData('expires_at', e.target.value)} className={INPUT} />
                    {err(errors.expires_at)}
                </label>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <label className="block">
                    {lbl(t('admin.coupons.form.descAr'))}
                    <input dir="rtl" value={data.description_ar} onChange={(e) => setData('description_ar', e.target.value)} className={INPUT} placeholder={t('admin.coupons.form.optional')} />
                    {err(errors.description_ar)}
                </label>
                <label className="block">
                    {lbl(t('admin.coupons.form.descEn'))}
                    <input dir="ltr" value={data.description_en} onChange={(e) => setData('description_en', e.target.value)} className={INPUT} placeholder={t('admin.coupons.form.optional')} />
                    {err(errors.description_en)}
                </label>
            </div>

            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="h-4 w-4 accent-brand-gold" />
                {t('admin.coupons.form.activeLabel')}
            </label>

            <div className="flex justify-end gap-2 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                <Button type="button" variant="secondary" onClick={onClose}>{t('admin.common.cancel')}</Button>
                <Button type="submit" variant="primary" disabled={processing || !isDirty}>{t('admin.coupons.form.save')}</Button>
            </div>
        </form>
    );
}

export default function CouponsIndex({ coupons, activeCount }: { coupons: Paginator<CouponRow>; activeCount: number }) {
    const { t } = useAdminT();
    const rc = useResizableColumns({ tableKey: 'coupons', columns: COLUMNS });
    const [editing, setEditing] = useState<CouponRow | 'new' | null>(null);

    const sar = t('admin.common.sar');
    const money = (n: number) => `${Math.round(n).toLocaleString()} ${sar}`;
    const dateOnly = (dt: string | null) => (dt ? dt.slice(0, 10) : null);

    const discountLabel = (c: CouponRow) => {
        if (c.type === 'free_shipping') return t('admin.coupons.freeDelivery');
        if (c.type === 'percentage') return c.max_discount ? `${c.value}% (${t('admin.coupons.maxCap', { amount: money(c.max_discount) })})` : `${c.value}%`;
        return money(c.value);
    };

    const windowLabel = (c: CouponRow) => {
        const s = dateOnly(c.starts_at);
        const e = dateOnly(c.expires_at);
        if (s && e) return `${s} → ${e}`;
        if (e) return t('admin.coupons.windowUntil', { date: e });
        if (s) return t('admin.coupons.windowFrom', { date: s });
        return t('admin.coupons.windowNone');
    };

    return (
        <AdminLayout title={t('admin.coupons.title')}>
            <Head title={t('admin.coupons.title')} />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm text-neutral-400">{t('admin.coupons.summary', { active: activeCount, total: coupons.total })}</span>
                    {rc.isDefault ? (
                        <span className="hidden items-center gap-1.5 text-xs text-neutral-500 lg:inline-flex">
                            <MoveHorizontal className="h-3.5 w-3.5" /> {t('admin.common.dragToResize')}
                        </span>
                    ) : (
                        <Button size="sm" variant="ghost" icon={Columns3} onClick={rc.resetAll}>{t('admin.common.resetColumns')}</Button>
                    )}
                </div>
                <Button variant="primary" icon={Plus} onClick={() => setEditing('new')}>{t('admin.coupons.newCoupon')}</Button>
            </div>

            <StickyScrollWrapper className="rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="min-w-full table-fixed text-sm" style={{ width: rc.tableWidth }}>
                    <thead className="border-b border-neutral-200 bg-neutral-50 text-left text-neutral-600 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-300">
                        <tr>
                            <ResizableTh colKey="code" width={rc.widths.code} resizeProps={rc.getResizeHandleProps('code')} resizing={rc.resizing === 'code'}>{t('admin.coupons.cols.code')}</ResizableTh>
                            <ResizableTh colKey="discount" width={rc.widths.discount} resizeProps={rc.getResizeHandleProps('discount')} resizing={rc.resizing === 'discount'}>{t('admin.coupons.cols.discount')}</ResizableTh>
                            <ResizableTh colKey="window" width={rc.widths.window} resizeProps={rc.getResizeHandleProps('window')} resizing={rc.resizing === 'window'}>{t('admin.coupons.cols.window')}</ResizableTh>
                            <ResizableTh colKey="usage" width={rc.widths.usage} resizeProps={rc.getResizeHandleProps('usage')} resizing={rc.resizing === 'usage'}>{t('admin.coupons.cols.usage')}</ResizableTh>
                            <ResizableTh colKey="status" width={rc.widths.status} resizeProps={rc.getResizeHandleProps('status')} resizing={rc.resizing === 'status'}>{t('admin.coupons.cols.status')}</ResizableTh>
                            <ResizableTh colKey="actions" width={rc.widths.actions} resizeProps={rc.getResizeHandleProps('actions')} resizing={rc.resizing === 'actions'} className="text-end">{t('admin.common.actions')}</ResizableTh>
                        </tr>
                    </thead>
                    <tbody>
                        {coupons.data.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-8 text-center text-neutral-400">{t('admin.coupons.empty')}</td></tr>
                        )}
                        {coupons.data.map((c) => (
                            <tr key={c.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td className="truncate px-4 py-3 font-mono font-medium text-neutral-800 dark:text-neutral-100">{c.code}</td>
                                <td className="truncate px-4 py-3 text-neutral-600 dark:text-neutral-300">{discountLabel(c)}</td>
                                <td className="truncate px-4 py-3 text-neutral-500">{windowLabel(c)}</td>
                                <td className="truncate px-4 py-3 text-neutral-500">
                                    {c.used_count}
                                    {c.usage_limit != null ? ` / ${c.usage_limit}` : ` / ${t('admin.coupons.unlimited')}`}
                                    {c.per_user_limit != null && <span className="ms-1 text-xs text-neutral-400">({t('admin.coupons.perUser', { n: c.per_user_limit })})</span>}
                                </td>
                                <td className="px-4 py-3">
                                    <span className={`rounded-full px-2 py-0.5 text-xs ${STATUS_STYLE[c.status] ?? STATUS_STYLE.inactive}`}>{t(`admin.coupons.status.${c.status}`)}</span>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center justify-end gap-2">
                                        <Button size="sm" variant="secondary" icon={Pencil} onClick={() => setEditing(c)}>{t('admin.common.edit')}</Button>
                                        {c.used_count === 0 && (
                                            <ConfirmDeleteButton
                                                itemName={c.code}
                                                reversible={false}
                                                onConfirm={() => router.delete(`/admin/coupons/${c.id}`, { preserveScroll: true })}
                                            />
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </StickyScrollWrapper>

            <Pagination paginator={coupons} />

            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                size="lg"
                title={editing && editing !== 'new' ? t('admin.coupons.form.editTitle', { code: editing.code }) : t('admin.coupons.form.newTitle')}
            >
                {editing !== null && (
                    <CouponForm key={editing === 'new' ? 'new' : editing.id} coupon={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
                )}
            </Modal>
        </AdminLayout>
    );
}
