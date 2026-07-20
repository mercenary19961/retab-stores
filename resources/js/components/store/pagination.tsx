import { router } from '@inertiajs/react';

export interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

/**
 * Brand-styled storefront pager driven by a Laravel paginator's `links`. Renders
 * nothing when everything fits on one page. Navigating scrolls back to the top of
 * the results (default Inertia behaviour) so a fresh page starts at the grid.
 * Pass `only` to fetch just the paginated prop (partial reload) instead of the
 * whole page.
 */
export default function StorePagination<T>({ paginator, only }: { paginator: Paginator<T>; only?: string[] }) {
    if (paginator.total <= paginator.data.length) {
        return null;
    }

    return (
        <nav className="mt-10 flex flex-wrap items-center justify-center gap-1.5">
            {paginator.links.map((link, i) => (
                <button
                    key={i}
                    type="button"
                    disabled={!link.url}
                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true, only })}
                    className={`min-w-9 rounded-full px-3 py-1.5 text-sm font-medium transition-colors ${
                        link.active
                            ? 'bg-brand-teal text-white'
                            : 'text-brand-teal hover:bg-brand-gold/15 disabled:pointer-events-none disabled:opacity-30'
                    }`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </nav>
    );
}
