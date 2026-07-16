import { Head, Link, router } from '@inertiajs/react';
import { Check, ExternalLink, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';

interface FieldChange {
    label: string;
    old: string;
    new: string;
}

interface LogRow {
    id: number;
    section: string;
    action: string;
    label: string | null;
    changes: FieldChange[];
    user: string | null;
    created_at: string | null;
    revertable: boolean;
    reverted_at: string | null;
    reverted_by: string | null;
    reverts_log_id: number | null;
    edit_url: string | null;
    fields: string[];
}

interface Paginated {
    data: LogRow[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

const ACTION_STYLES: Record<string, string> = {
    created: 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300',
    updated: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    deleted: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    restored: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
};

export default function ChangeLogIndex({ logs, highlight = null }: { logs: Paginated; highlight?: number | null }) {
    // Scroll to and briefly flag the entry linked from a conflict banner.
    const [flagged, setFlagged] = useState<number | null>(highlight);
    useEffect(() => {
        if (!highlight) return;
        setFlagged(highlight);
        document.getElementById(`log-${highlight}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const timer = setTimeout(() => setFlagged(null), 3500);
        return () => clearTimeout(timer);
    }, [highlight]);

    const revert = (row: LogRow) => {
        if (!window.confirm(`Revert this ${row.section.toLowerCase()} change? A new entry will record the revert.`)) return;
        router.post(`/admin/change-log/${row.id}/revert`, {}, { preserveScroll: true });
    };

    return (
        <AdminLayout title="Change Log">
            <Head title="Change Log" />

            <div className="overflow-x-auto rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                {logs.data.length === 0 ? (
                    <p className="p-6 text-sm text-neutral-400">No tracked changes yet.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-500 dark:bg-neutral-800/40">
                            <tr className="border-b border-neutral-100 dark:border-neutral-800">
                                <th className="px-4 py-3 font-medium">When</th>
                                <th className="px-4 py-3 font-medium">Section</th>
                                <th className="px-4 py-3 font-medium">Action</th>
                                <th className="px-4 py-3 font-medium">Item</th>
                                <th className="px-4 py-3 font-medium">Changes</th>
                                <th className="px-4 py-3 font-medium">By</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((row) => (
                                <tr
                                    key={row.id}
                                    id={`log-${row.id}`}
                                    className={`border-b border-neutral-100 align-top transition-colors duration-1000 last:border-b-0 dark:border-neutral-800 ${
                                        flagged === row.id
                                            ? 'bg-amber-500/15 ring-2 ring-inset ring-amber-500/60'
                                            : 'hover:bg-neutral-50 dark:hover:bg-neutral-800/30'
                                    }`}
                                >
                                    <td className="whitespace-nowrap px-4 py-3 text-neutral-500">{row.created_at}</td>
                                    <td className="whitespace-nowrap px-4 py-3">{row.section}</td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span
                                            className={`rounded px-2 py-0.5 text-xs font-medium ${
                                                ACTION_STYLES[row.action] ?? 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300'
                                            }`}
                                        >
                                            {row.action.replace(/_/g, ' ')}
                                        </span>
                                        {row.reverts_log_id !== null && (
                                            <Link
                                                href={`/admin/change-log?highlight=${row.reverts_log_id}`}
                                                className="ms-1 rounded bg-neutral-100 px-1.5 py-0.5 text-[11px] text-neutral-500 transition-colors hover:bg-neutral-200 hover:text-neutral-700 dark:bg-neutral-800 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"
                                            >
                                                revert of #{row.reverts_log_id}
                                            </Link>
                                        )}
                                    </td>
                                    <td className="max-w-40 truncate px-4 py-3">{row.label ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        {row.changes.length === 0 ? (
                                            <span className="text-neutral-400">—</span>
                                        ) : (
                                            <ul className="space-y-0.5">
                                                {row.changes.map((c, i) => (
                                                    <li key={i} className="text-xs" dir="auto">
                                                        <span className="text-neutral-500">{c.label}: </span>
                                                        <span className="text-red-500/70 line-through dark:text-red-400/70">{c.old || '—'}</span>
                                                        <span className="mx-1 text-neutral-400">→</span>
                                                        <span className="font-medium text-green-600 dark:text-green-400">{c.new || '—'}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3">{row.user ?? '—'}</td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            {row.edit_url && (
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    icon={ExternalLink}
                                                    href={`${row.edit_url}${row.fields.length ? `?highlight=${row.fields.join(',')}` : ''}`}
                                                >
                                                    Open
                                                </Button>
                                            )}
                                            {row.reverted_at ? (
                                                <span
                                                    className="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2.5 py-1 text-xs text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400"
                                                    title={`Reverted by ${row.reverted_by ?? 'unknown'} at ${row.reverted_at}`}
                                                >
                                                    <Check className="h-3 w-3" /> Reverted
                                                </span>
                                            ) : row.revertable ? (
                                                <Button size="sm" variant="danger" icon={RotateCcw} onClick={() => revert(row)}>
                                                    Revert
                                                </Button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {logs.total > logs.data.length && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {logs.links.map((link, i) => (
                        <button
                            key={i}
                            type="button"
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                            className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-600 disabled:opacity-40 dark:text-neutral-300'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
