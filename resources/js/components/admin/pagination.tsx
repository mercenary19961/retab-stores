import { router } from '@inertiajs/react';

export interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

/**
 * Standard admin pager driven by a Laravel paginator's `links`. Renders nothing
 * when everything fits on one page. Pass `only` to partial-reload a single prop
 * (e.g. a secondary list that paginates independently of the rest of the page).
 */
export default function Pagination<T>({ paginator, only, className = 'mt-4' }: { paginator: Paginator<T>; only?: string[]; className?: string }) {
    if (paginator.total <= paginator.data.length) {
        return null;
    }

    return (
        <div className={`flex flex-wrap gap-1 ${className}`}>
            {paginator.links.map((link, i) => (
                <button
                    key={i}
                    type="button"
                    disabled={!link.url}
                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true, only })}
                    className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-600 disabled:opacity-40 dark:text-neutral-300'}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}
