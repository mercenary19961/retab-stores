import { Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';

interface MatchRow {
    product_id: number;
    name: string;
    sku: string;
    old: number;
    new: number;
}

interface Row {
    line?: number;
    smacc_sku?: string;
    barcode?: string;
    stock?: number | null;
    error?: string | null;
    product_id?: number;
    name?: string;
    sku?: string;
}

interface Diff {
    matched: MatchRow[];
    unchanged: Row[];
    unmatched: Row[];
    invalid: Row[];
}

function Stat({ label, value, tone }: { label: string; value: number; tone?: string }) {
    return (
        <div className="rounded-lg border border-neutral-200 bg-white px-4 py-3 dark:border-neutral-800 dark:bg-neutral-900">
            <div className={`text-2xl font-bold ${tone ?? ''}`}>{value}</div>
            <div className="text-xs text-neutral-500">{label}</div>
        </div>
    );
}

export default function StockImportPreview({ token, diff }: { token: string; diff: Diff }) {
    const [busy, setBusy] = useState(false);

    const apply = () => {
        router.post(
            '/admin/stock-import/apply',
            { token },
            { onStart: () => setBusy(true), onFinish: () => setBusy(false) },
        );
    };

    const nothingToApply = diff.matched.length === 0;

    return (
        <AdminLayout title="Review import">
            <Head title="Review import" />

            <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Stat label="To update" value={diff.matched.length} tone="text-blue-600 dark:text-blue-400" />
                <Stat label="Unchanged" value={diff.unchanged.length} />
                <Stat label="Unmatched" value={diff.unmatched.length} tone={diff.unmatched.length ? 'text-amber-600 dark:text-amber-400' : ''} />
                <Stat label="Invalid rows" value={diff.invalid.length} tone={diff.invalid.length ? 'text-red-600 dark:text-red-400' : ''} />
            </div>

            <div className="mb-6 flex gap-3">
                <Button variant="success" icon={Check} onClick={apply} disabled={busy || nothingToApply}>
                    {nothingToApply ? 'Nothing to apply' : `Apply ${diff.matched.length} update(s)`}
                </Button>
                <Button href="/admin/stock-import" variant="secondary">Cancel</Button>
            </div>

            {diff.matched.length > 0 && (
                <section className="mb-6 overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="border-b border-neutral-200 px-4 py-2 font-bold dark:border-neutral-800">Stock changes</h2>
                    <table className="w-full text-sm">
                        <thead className="text-left text-neutral-500">
                            <tr>
                                <th className="px-4 py-2 font-medium">Product</th>
                                <th className="px-4 py-2 font-medium">SKU</th>
                                <th className="px-4 py-2 text-right font-medium">Current</th>
                                <th className="px-4 py-2 text-right font-medium">New</th>
                                <th className="px-4 py-2 text-right font-medium">Δ</th>
                            </tr>
                        </thead>
                        <tbody>
                            {diff.matched.map((m) => {
                                const delta = m.new - m.old;
                                return (
                                    <tr key={m.product_id} className="border-t border-neutral-100 dark:border-neutral-800">
                                        <td className="px-4 py-2">{m.name}</td>
                                        <td className="px-4 py-2 font-mono text-neutral-500">{m.sku}</td>
                                        <td className="px-4 py-2 text-right">{m.old}</td>
                                        <td className="px-4 py-2 text-right font-medium">{m.new}</td>
                                        <td className={`px-4 py-2 text-right ${delta > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                            {delta > 0 ? `+${delta}` : delta}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </section>
            )}

            {diff.unmatched.length > 0 && (
                <section className="mb-6 overflow-hidden rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40">
                    <h2 className="border-b border-amber-200 px-4 py-2 font-bold text-amber-800 dark:border-amber-900 dark:text-amber-200">
                        Unmatched rows (skipped — no product with this SMACC SKU / barcode)
                    </h2>
                    <table className="w-full text-sm">
                        <thead className="text-left text-amber-700 dark:text-amber-300">
                            <tr>
                                <th className="px-4 py-2 font-medium">Line</th>
                                <th className="px-4 py-2 font-medium">SMACC SKU</th>
                                <th className="px-4 py-2 font-medium">Barcode</th>
                                <th className="px-4 py-2 text-right font-medium">Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            {diff.unmatched.map((r, i) => (
                                <tr key={i} className="border-t border-amber-200/60 dark:border-amber-900/60">
                                    <td className="px-4 py-2">{r.line}</td>
                                    <td className="px-4 py-2 font-mono">{r.smacc_sku || '—'}</td>
                                    <td className="px-4 py-2 font-mono">{r.barcode || '—'}</td>
                                    <td className="px-4 py-2 text-right">{r.stock}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}

            {diff.invalid.length > 0 && (
                <section className="overflow-hidden rounded-lg border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40">
                    <h2 className="border-b border-red-200 px-4 py-2 font-bold text-red-800 dark:border-red-900 dark:text-red-200">Invalid rows (skipped)</h2>
                    <ul className="px-4 py-2 text-sm text-red-700 dark:text-red-300">
                        {diff.invalid.map((r, i) => (
                            <li key={i}>Line {r.line}: {r.error}</li>
                        ))}
                    </ul>
                </section>
            )}
        </AdminLayout>
    );
}
