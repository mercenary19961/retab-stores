import { Head, router, useForm } from '@inertiajs/react';
import { Download, History, Upload } from 'lucide-react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import ExportButtons from '@/components/admin/export-buttons';
import { useAdminT } from '@/i18n/use-admin-t';

interface LastSynced {
    at: string | null;
    hours: number | null;
    stale: boolean;
}

interface HistoryRow {
    id: number;
    updated: number;
    unmatched: number;
    user: string | null;
    created_at: string | null;
    reverted_at: string | null;
}

export default function StockImportIndex({ lastSynced, history }: { lastSynced: LastSynced; history: HistoryRow[] }) {
    const { t } = useAdminT();
    const { setData, post, processing, errors } = useForm<{ file: File | null }>({ file: null });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/stock-import/preview', { forceFormData: true });
    };

    const undo = (id: number) => {
        if (!window.confirm(t('admin.inventory.undoConfirm'))) return;
        router.post(`/admin/stock-import/${id}/undo`, {}, { preserveScroll: true });
    };

    return (
        <AdminLayout title={t('admin.inventory.title')}>
            <Head title={t('admin.inventory.title')} />

            {/* Last-synced indicator */}
            <div
                className={`mb-6 rounded-lg border px-4 py-3 text-sm ${
                    lastSynced.stale
                        ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200'
                        : 'border-neutral-200 bg-white text-neutral-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300'
                }`}
            >
                {lastSynced.at ? (
                    <>
                        {t('admin.inventory.syncedPrefix')} <b>{lastSynced.at}</b>
                        {lastSynced.hours !== null && ` ${t('admin.inventory.hoursAgo', { hours: lastSynced.hours })}`}
                        {lastSynced.stale && ` ${t('admin.inventory.staleNote')}`}
                    </>
                ) : (
                    t('admin.inventory.neverSynced')
                )}
            </div>

            {/* Current-stock export (feeds the daily SMACC reconciliation) */}
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-neutral-200 bg-white px-4 py-3 dark:border-neutral-800 dark:bg-neutral-900">
                <div className="text-sm">
                    <p className="flex items-center gap-2 font-semibold"><Download className="h-4 w-4 text-brand-gold" /> {t('admin.inventory.exportTitle')}</p>
                    <p className="text-neutral-500">{t('admin.inventory.exportDesc')}</p>
                </div>
                <ExportButtons base="/admin/stock-import/export" />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <section className="rounded-lg border border-neutral-200 bg-white p-4 lg:col-span-1 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="mb-2 flex items-center gap-2 font-bold"><Upload className="h-4 w-4 text-brand-gold" /> {t('admin.inventory.uploadTitle')}</h2>
                    <p className="mb-3 text-sm text-neutral-500">{t('admin.inventory.uploadDesc')}</p>
                    <form onSubmit={submit}>
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                            className="block w-full text-sm"
                        />
                        {errors.file && <p className="mt-1 text-xs text-red-500">{errors.file}</p>}
                        <Button type="submit" variant="primary" disabled={processing} className="mt-4 w-full">
                            {t('admin.inventory.previewChanges')}
                        </Button>
                    </form>
                </section>

                <section className="rounded-lg border border-neutral-200 bg-white p-4 lg:col-span-2 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="mb-3 flex items-center gap-2 font-bold"><History className="h-4 w-4 text-brand-gold" /> {t('admin.inventory.recentImports')}</h2>
                    {history.length === 0 ? (
                        <p className="text-sm text-neutral-400">{t('admin.inventory.noImports')}</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="text-left text-neutral-500">
                                <tr>
                                    <th className="py-2 font-medium">{t('admin.inventory.when')}</th>
                                    <th className="py-2 font-medium">{t('admin.inventory.by')}</th>
                                    <th className="py-2 font-medium">{t('admin.inventory.updated')}</th>
                                    <th className="py-2 font-medium">{t('admin.inventory.unmatched')}</th>
                                    <th className="py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.map((row) => (
                                    <tr key={row.id} className="border-t border-neutral-100 dark:border-neutral-800">
                                        <td className="py-2">{row.created_at}</td>
                                        <td className="py-2">{row.user ?? '—'}</td>
                                        <td className="py-2">{row.updated}</td>
                                        <td className="py-2">
                                            {row.unmatched > 0 ? <span className="text-amber-600 dark:text-amber-400">{row.unmatched}</span> : 0}
                                        </td>
                                        <td className="py-2 text-right">
                                            {row.reverted_at ? (
                                                <span className="text-xs text-neutral-400">{t('admin.inventory.reverted')}</span>
                                            ) : (
                                                <Button size="sm" variant="danger" onClick={() => undo(row.id)}>{t('admin.inventory.undo')}</Button>
                                            )}
                                        </td>
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
