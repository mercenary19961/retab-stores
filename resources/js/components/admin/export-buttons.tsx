import { Download } from 'lucide-react';

type Params = Record<string, string | number | null | undefined>;

/**
 * CSV / Excel / JSON download links for an admin table. `base` is the export
 * endpoint; `params` are the current filters/sort (falsy values are dropped) so
 * the download matches exactly what's on screen. Plain <a> tags so the browser
 * downloads instead of an Inertia visit.
 */
export default function ExportButtons({ base, params = {} }: { base: string; params?: Params }) {
    const url = (format: 'csv' | 'xlsx' | 'json') => {
        const p = new URLSearchParams({ format });
        Object.entries(params).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') p.set(k, String(v));
        });
        return `${base}?${p.toString()}`;
    };

    return (
        <div className="flex items-center gap-2 text-sm">
            <span className="flex items-center gap-1.5 text-neutral-400">
                <Download className="h-4 w-4" /> Export
            </span>
            {(['csv', 'xlsx', 'json'] as const).map((f) => (
                <a
                    key={f}
                    href={url(f)}
                    className="rounded-lg border border-neutral-300 px-3 py-1.5 font-medium text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                >
                    {f === 'xlsx' ? 'Excel' : f.toUpperCase()}
                </a>
            ))}
        </div>
    );
}
