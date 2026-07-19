import { Head, useForm } from '@inertiajs/react';
import { BadgePercent, X } from 'lucide-react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import StickyScrollWrapper from '@/components/admin/sticky-scroll-wrapper';
import { useAdminT } from '@/i18n/use-admin-t';

interface Matched { product_id: number; name: string; sku: string; price: number; percent: number; new: number }
interface Unmatched { line: number; sku: string }
interface Diff { matched: Matched[]; unmatched: Unmatched[]; invalid: Unmatched[] }

const INPUT =
    'mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 focus:border-brand-gold focus:outline-none focus:ring-1 focus:ring-brand-gold dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100';

export default function DiscountPreview({ token, diff }: { token: string; diff: Diff }) {
    const { t } = useAdminT();
    const sar = t('admin.common.sar');
    const money = (n: number) => `${Math.round(n).toLocaleString()} ${sar}`;

    const form = useForm({ token, starts_at: '', ends_at: '' });
    const apply = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/discounts/import/apply');
    };

    return (
        <AdminLayout title={t('admin.discounts.preview.title')}>
            <Head title={t('admin.discounts.preview.title')} />

            <div className="mb-4 flex flex-wrap gap-3 text-sm">
                <span className="rounded-full bg-green-100 px-3 py-1 text-green-800 dark:bg-green-950 dark:text-green-200">{t('admin.discounts.preview.matched', { n: diff.matched.length })}</span>
                <span className="rounded-full bg-amber-100 px-3 py-1 text-amber-800 dark:bg-amber-950 dark:text-amber-200">{t('admin.discounts.preview.unmatched', { n: diff.unmatched.length })}</span>
                <span className="rounded-full bg-red-100 px-3 py-1 text-red-700 dark:bg-red-950 dark:text-red-300">{t('admin.discounts.preview.invalid', { n: diff.invalid.length })}</span>
            </div>

            {diff.matched.length === 0 ? (
                <p className="rounded-lg border border-neutral-200 bg-white p-6 text-center text-sm text-neutral-400 dark:border-neutral-800 dark:bg-neutral-900">{t('admin.discounts.preview.none')}</p>
            ) : (
                <StickyScrollWrapper className="rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                    <table className="min-w-full text-sm">
                        <thead className="border-b border-neutral-200 text-left text-neutral-600 dark:border-neutral-800 dark:text-neutral-300">
                            <tr>
                                <th className="px-4 py-2">{t('admin.discounts.preview.cols.product')}</th>
                                <th className="px-4 py-2">{t('admin.discounts.preview.cols.sku')}</th>
                                <th className="px-4 py-2">{t('admin.discounts.preview.cols.price')}</th>
                                <th className="px-4 py-2">{t('admin.discounts.preview.cols.percent')}</th>
                                <th className="px-4 py-2">{t('admin.discounts.preview.cols.newPrice')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {diff.matched.map((m) => (
                                <tr key={m.product_id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                    <td className="px-4 py-2" dir="auto">{m.name}</td>
                                    <td className="px-4 py-2 font-mono text-xs text-neutral-500">{m.sku}</td>
                                    <td className="whitespace-nowrap px-4 py-2 text-neutral-400 line-through">{money(m.price)}</td>
                                    <td className="whitespace-nowrap px-4 py-2 text-green-600 dark:text-green-400">-{m.percent}%</td>
                                    <td className="whitespace-nowrap px-4 py-2 font-medium text-neutral-800 dark:text-neutral-100">{money(m.new)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </StickyScrollWrapper>
            )}

            {diff.unmatched.length > 0 && (
                <p className="mt-3 text-xs text-neutral-500">{t('admin.discounts.preview.unmatchedSkus')}: {diff.unmatched.map((u) => u.sku).join(', ')}</p>
            )}

            <form onSubmit={apply} className="mt-6 rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                <p className="mb-3 text-sm font-medium text-neutral-600 dark:text-neutral-300">{t('admin.discounts.preview.window')}</p>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm">
                        <span className="text-neutral-600 dark:text-neutral-300">{t('admin.discounts.bulk.startsAt')}</span>
                        <input type="datetime-local" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} className={INPUT} />
                    </label>
                    <label className="block text-sm">
                        <span className="text-neutral-600 dark:text-neutral-300">{t('admin.discounts.bulk.endsAt')}</span>
                        <input type="datetime-local" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} className={INPUT} />
                        {form.errors.ends_at && <span className="mt-1 block text-xs text-red-500">{form.errors.ends_at}</span>}
                    </label>
                </div>
                <div className="mt-4 flex justify-end gap-2">
                    <Button type="button" variant="secondary" icon={X} href="/admin/discounts">{t('admin.common.cancel')}</Button>
                    <Button type="submit" variant="primary" icon={BadgePercent} disabled={form.processing || diff.matched.length === 0}>{t('admin.discounts.preview.applyN', { n: diff.matched.length })}</Button>
                </div>
            </form>
        </AdminLayout>
    );
}
