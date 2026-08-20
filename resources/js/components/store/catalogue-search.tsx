import { useSearchIndex } from '@/hooks/use-search-index';
import { useLocalized } from '@/lib/localize';
import { correctQuery, searchProducts, serverWouldMatch, type SearchResult } from '@/lib/search';
import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

/**
 * The catalogue page's own search box, with a live typeahead.
 *
 * 🔑 Shares `useSearchIndex` and `searchProducts` with the navbar overlay, so the
 * two can never disagree about what matches — and on this page they are on screen
 * at the same time. Everything about HOW matching works (Arabic letter folding,
 * typo tolerance, synonyms, ranking) lives in `lib/search.ts`; this file only
 * renders it. The index is still fetched lazily rather than shipped as a prop, so
 * the server-rendered page a crawler sees is unchanged.
 */
export default function CatalogueSearch({ initialQuery, onSubmit }: { initialQuery: string; onSubmit: (q: string) => void }) {
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');
    const { index, load: loadIndex } = useSearchIndex();

    const [q, setQ] = useState(initialQuery);
    const [open, setOpen] = useState(false);
    const boxRef = useRef<HTMLDivElement>(null);

    const term = q.trim();
    const results: SearchResult[] = useMemo(() => (index ? searchProducts(index, term, 8) : []), [index, term]);

    /**
     * What "see all results" should search for. The results page matches by
     * substring only, so a query that matched purely on typo tolerance has to be
     * corrected first — otherwise the link takes a shopper from five suggestions to
     * an empty page. null = offer no link at all.
     */
    const submitTerm = useMemo(() => {
        if (!index || term === '') return null;
        if (serverWouldMatch(index, term)) return term;

        return correctQuery(index, term, results[0]);
    }, [index, term, results]);

    // Close on outside click / Escape.
    useEffect(() => {
        const onDown = (e: MouseEvent) => boxRef.current && !boxRef.current.contains(e.target as Node) && setOpen(false);
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        window.addEventListener('mousedown', onDown);
        window.addEventListener('keydown', onKey);

        return () => {
            window.removeEventListener('mousedown', onDown);
            window.removeEventListener('keydown', onKey);
        };
    }, []);

    const onChange = (value: string) => {
        setQ(value);
        loadIndex();
        setOpen(value.trim().length >= 1);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setOpen(false);
        onSubmit(submitTerm ?? term);
    };

    const showDropdown = open && term.length >= 1;

    return (
        <div ref={boxRef} className="relative min-w-0 flex-1 sm:max-w-xs">
            <form onSubmit={submit}>
                <Search className="text-brand-gold pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                <input
                    type="search"
                    value={q}
                    onChange={(e) => onChange(e.target.value)}
                    onFocus={() => {
                        loadIndex();
                        if (term.length >= 1) setOpen(true);
                    }}
                    placeholder={t('catalogue.searchPlaceholder')}
                    aria-label={t('nav.search')}
                    autoComplete="off"
                    data-testid="catalogue-search"
                    className="border-brand-gold/30 text-brand-teal placeholder:text-brand-teal/40 focus:border-brand-gold focus:ring-brand-gold/25 w-full rounded-full border bg-white py-2 ps-9 pe-4 text-sm focus:ring-2 focus:outline-none"
                />
            </form>

            {showDropdown && (
                <div className="border-brand-gold/20 absolute z-30 mt-2 w-full overflow-hidden rounded-2xl border bg-white shadow-lg sm:min-w-[22rem]">
                    {results.length === 0 ? (
                        <p className="text-brand-teal/60 px-4 py-3 text-sm">{index === null ? t('catalogue.searching') : t('catalogue.noResults')}</p>
                    ) : (
                        <ul className="brand-scrollbar max-h-96 overflow-y-auto py-1">
                            {results.map((r) => (
                                <li key={r.slug}>
                                    <Link
                                        href={`/products/${r.slug}`}
                                        onClick={() => setOpen(false)}
                                        className="hover:bg-brand-cream flex items-center gap-3 px-3 py-2 transition-colors"
                                    >
                                        {r.image ? (
                                            <img src={r.image} alt="" className="size-11 shrink-0 rounded-lg object-cover" />
                                        ) : (
                                            <span className="bg-brand-cream flex size-11 shrink-0 items-center justify-center rounded-lg text-lg">
                                                🌴
                                            </span>
                                        )}
                                        <span className="min-w-0 flex-1">
                                            <span className="text-brand-teal block truncate text-sm font-medium">{localized(r, 'name')}</span>
                                            {r.coming_soon ? (
                                                <span className="text-brand-gold text-xs font-semibold">{t('catalogue.comingSoon')}</span>
                                            ) : (
                                                <span className="text-brand-teal/70 text-xs whitespace-nowrap">
                                                    {r.effective_price.toFixed(2)} {currency}
                                                    {r.on_sale && (
                                                        <span className="text-brand-teal/40 ms-1.5 line-through">{r.price.toFixed(2)}</span>
                                                    )}
                                                </span>
                                            )}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                            {submitTerm && (
                                <li className="border-brand-gold/10 border-t">
                                    <button
                                        type="button"
                                        onClick={submit}
                                        className="text-brand-gold hover:bg-brand-cream w-full px-4 py-2.5 text-start text-sm font-medium transition-colors"
                                    >
                                        {submitTerm !== term ? t('search.viewAllCorrected', { term: submitTerm }) : t('catalogue.searchViewAll')}
                                    </button>
                                </li>
                            )}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
