import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';

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
    const { setData, post, processing, errors } = useForm<{ file: File | null }>({ file: null });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/stock-import/preview', { forceFormData: true });
    };

    const undo = (id: number) => {
        if (!window.confirm('Undo this import and restore the previous stock?')) return;
        router.post(`/admin/stock-import/${id}/undo`, {}, { preserveScroll: true });
    };

    return (
        <AdminLayout title="Inventory sync">
            <Head title="Inventory sync" />

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
                        Stock last synced: <b>{lastSynced.at}</b>
                        {lastSynced.hours !== null && ` (${lastSynced.hours}h ago)`}
                        {lastSynced.stale && ' — over 24h ago, please run today’s import.'}
                    </>
                ) : (
                    'Stock has never been synced from SMACC. Upload today’s export to begin.'
                )}
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <section className="rounded-lg border border-neutral-200 bg-white p-4 lg:col-span-1 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="mb-2 font-bold">Upload SMACC export</h2>
                    <p className="mb-3 text-sm text-neutral-500">
                        Export inventory from SMACC, Save As <b>CSV</b>, and upload it here. Rows are matched by SMACC SKU
                        (or barcode). You&apos;ll review the changes before anything is applied.
                    </p>
                    <form onSubmit={submit}>
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                            className="block w-full text-sm"
                        />
                        {errors.file && <p className="mt-1 text-xs text-red-500">{errors.file}</p>}
                        <button
                            type="submit"
                            disabled={processing}
                            className="mt-4 w-full rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-800 disabled:opacity-60 dark:bg-white dark:text-neutral-900"
                        >
                            Preview changes
                        </button>
                    </form>
                </section>

                <section className="rounded-lg border border-neutral-200 bg-white p-4 lg:col-span-2 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="mb-3 font-bold">Recent imports</h2>
                    {history.length === 0 ? (
                        <p className="text-sm text-neutral-400">No imports yet.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="text-left text-neutral-500">
                                <tr>
                                    <th className="py-2 font-medium">When</th>
                                    <th className="py-2 font-medium">By</th>
                                    <th className="py-2 font-medium">Updated</th>
                                    <th className="py-2 font-medium">Unmatched</th>
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
                                                <span className="text-xs text-neutral-400">reverted</span>
                                            ) : (
                                                <button type="button" onClick={() => undo(row.id)} className="text-red-600 hover:underline dark:text-red-400">
                                                    Undo
                                                </button>
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
