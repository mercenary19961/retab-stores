import { buildIndex, type IndexedProduct, type SearchProduct } from '@/lib/search';
import { useCallback, useEffect, useState } from 'react';

/**
 * The catalogue search index, fetched once per page session and shared by every
 * search surface (the navbar overlay and the catalogue's own box).
 *
 * 🔑 The cache is MODULE-level, not per-hook. Both surfaces exist on `/shop` at the
 * same time, and a per-component fetch would pull the whole index twice on the one
 * page where a shopper is most likely to use it. Inertia keeps the module alive
 * across navigations, so moving between pages costs nothing either.
 *
 * Fetched lazily on first interaction rather than shipped as an Inertia prop, so
 * the server-rendered HTML a crawler sees is unchanged — that was the original
 * reason for this design and it still holds.
 */
let cache: IndexedProduct[] | null = null;
let inFlight: Promise<IndexedProduct[]> | null = null;

function fetchIndex(): Promise<IndexedProduct[]> {
    if (cache) return Promise.resolve(cache);
    // Share one request between concurrent callers rather than racing two.
    inFlight ??= fetch('/shop/search-index', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => {
            if (!r.ok) throw new Error(String(r.status));

            return r.json();
        })
        .then((d: { products: SearchProduct[] }) => {
            cache = buildIndex(d.products ?? []);

            return cache;
        })
        .catch((e) => {
            inFlight = null; // let the next interaction retry

            throw e;
        });

    return inFlight;
}

export function useSearchIndex() {
    const [index, setIndex] = useState<IndexedProduct[] | null>(cache);
    const [failed, setFailed] = useState(false);

    const load = useCallback(() => {
        if (cache) {
            setIndex(cache);

            return;
        }
        setFailed(false);
        fetchIndex()
            .then(setIndex)
            .catch(() => setFailed(true));
    }, []);

    // Keep a late-mounting surface in step with an index another one already loaded.
    useEffect(() => {
        if (!index && cache) setIndex(cache);
    }, [index]);

    return { index, failed, load };
}
