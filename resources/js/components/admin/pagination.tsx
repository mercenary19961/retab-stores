import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

// Page sizes offered by the "per page" selector (mirrors the backend whitelist).
const BASE_PER_PAGE = [10, 25, 50, 100];

/**
 * Standard admin pager driven by a Laravel paginator's `links`. Pass `perPage`
 * (the active page size) to also render a "Per page" selector — changing it
 * merges `per_page` into the current URL and jumps back to page 1, preserving
 * every other filter. Pass `only` to partial-reload a single prop. Renders
 * nothing only when everything fits on one page AND no selector was requested.
 */
export default function Pagination<T>({
    paginator,
    only,
    className = 'mt-4',
    perPage,
}: {
    paginator: Paginator<T>;
    only?: string[];
    className?: string;
    perPage?: number;
}) {
    const { t } = useTranslation();
    const hasPages = paginator.total > paginator.data.length;

    if (!hasPages && perPage === undefined) {
        return null;
    }

    const changePerPage = (value: string) => {
        const params = Object.fromEntries(new URLSearchParams(window.location.search));
        params.per_page = value;
        delete params.page; // a bigger/smaller page invalidates the current page number
        router.get(window.location.pathname, params, { preserveState: true, preserveScroll: true, only });
    };

    // Merge the active size in so a table's own default (e.g. 20/30) still shows.
    const options = perPage !== undefined ? Array.from(new Set([...BASE_PER_PAGE, perPage])).sort((a, b) => a - b) : [];

    return (
        <div className={`flex flex-wrap items-center justify-between gap-3 ${className}`}>
            <div className="flex flex-wrap gap-1">
                {hasPages &&
                    paginator.links.map((link, i) => (
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

            {perPage !== undefined && (
                <label className="flex items-center gap-2 text-sm whitespace-nowrap text-neutral-500 dark:text-neutral-400">
                    {t('admin.common.perPage')}
                    <select
                        value={perPage}
                        onChange={(e) => changePerPage(e.target.value)}
                        className="rounded border border-neutral-300 bg-white px-2 py-1 text-sm text-neutral-800 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
                    >
                        {options.map((n) => (
                            <option key={n} value={n}>
                                {n}
                            </option>
                        ))}
                    </select>
                </label>
            )}
        </div>
    );
}
