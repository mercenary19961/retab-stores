import { useSearchIndex } from '@/hooks/use-search-index';
import { useLocalized } from '@/lib/localize';
import { correctQuery, searchProducts, serverWouldMatch, type SearchResult } from '@/lib/search';
import { router } from '@inertiajs/react';
import { Loader2, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';

const LIMIT = 7;

/**
 * Site-wide product search, opened from the navbar.
 *
 * 🔴 PORTALLED TO <body>, and that is not optional. The navbar writes
 * `header.style.transform` on every scroll tick, and a transformed ancestor becomes
 * the containing block for `position: fixed` descendants — even an identity
 * transform. A fixed overlay rendered inside <header> therefore resolves against
 * the header's own ~69px box the moment the visitor has scrolled once. The mobile
 * drawer hit exactly this and it looked intermittent, because an unscrolled page is
 * fine. Anything fixed that lives in this header has the same problem.
 *
 * An overlay rather than an inline field because row 1 of the navbar is a
 * mathematically-centred three-column grid — it is already documented as colliding
 * with the logo at 320px when one extra button is added — and row 2 is a
 * space-between nav that an input would unbalance. The overlay also gives the
 * results room to carry thumbnails, which an inline dropdown under a narrow field
 * does not.
 */
export default function SearchOverlay({ open, onClose }: { open: boolean; onClose: () => void }) {
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');
    const { index, failed, load } = useSearchIndex();

    const [q, setQ] = useState('');
    const [active, setActive] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLUListElement>(null);

    const term = q.trim();
    const results: SearchResult[] = useMemo(() => (index ? searchProducts(index, term, LIMIT) : []), [index, term]);

    /**
     * What "see all results" should actually search for.
     *
     * The results page matches by substring, so a query that only matched fuzzily
     * has to be corrected or the link is a trap. null = offer no link at all.
     */
    const submitTerm = useMemo(() => {
        if (!index || term === '') return null;
        if (serverWouldMatch(index, term)) return term;

        return correctQuery(index, term, results[0]);
    }, [index, term, results]);

    // Load the index and focus the field as soon as the overlay opens; reset the
    // query on close so the next open starts clean.
    useEffect(() => {
        if (!open) return;
        load();
        const id = window.setTimeout(() => inputRef.current?.focus(), 30);

        return () => window.clearTimeout(id);
    }, [open, load]);

    useEffect(() => {
        if (!open) {
            setQ('');
            setActive(0);
        }
    }, [open]);

    useEffect(() => setActive(0), [term]);

    // Lock the page behind the overlay. Restores whatever overflow was there rather
    // than assuming 'visible', so this composes with anything else that locks.
    useEffect(() => {
        if (!open) return;
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, [open]);

    // Keep the highlighted row in view when arrowing past the fold.
    useEffect(() => {
        listRef.current?.children[active]?.scrollIntoView({ block: 'nearest' });
    }, [active]);

    /**
     * Escape closes, from anywhere.
     *
     * ⚠️ Deliberately on the document and not only on the input's own onKeyDown.
     * Focus is moved into the field on a timer, so a fast Escape lands on <body>
     * and an input-scoped handler never sees it — and once the shopper has clicked
     * a result row or the scrollbar, focus has left the field anyway. Caught in a
     * browser: pressing Escape right after opening left the overlay up AND the page
     * behind it scroll-locked.
     */
    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) return null;

    const go = (url: string) => {
        onClose();
        router.visit(url);
    };

    // Escape is handled on the document (above), not here.
    const onKeyDown = (e: React.KeyboardEvent) => {
        if (results.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((i) => (i + 1) % results.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((i) => (i - 1 + results.length) % results.length);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // Enter on a highlighted row opens that product; the row is what the
        // shopper is looking at, so it beats a generic results page.
        if (results[active]) return go(`/products/${results[active].slug}`);
        if (submitTerm) go(`/shop?q=${encodeURIComponent(submitTerm)}`);
    };

    const loading = index === null && !failed;
    const corrected = submitTerm !== null && submitTerm !== term;

    return createPortal(
        <div className="fixed inset-0 z-[60]" role="dialog" aria-modal="true" aria-label={t('nav.search')}>
            <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />

            <div className="relative mx-auto mt-[8vh] w-[min(42rem,92vw)] overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form onSubmit={submit} className="border-brand-gold/15 flex items-center gap-3 border-b px-4 py-3">
                    {loading ? (
                        <Loader2 className="text-brand-gold size-5 shrink-0 animate-spin" />
                    ) : (
                        <Search className="text-brand-gold size-5 shrink-0" />
                    )}
                    <input
                        ref={inputRef}
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder={t('catalogue.searchPlaceholder')}
                        aria-label={t('nav.search')}
                        autoComplete="off"
                        className="text-brand-teal placeholder:text-brand-teal/40 min-w-0 flex-1 bg-transparent text-base outline-none"
                    />
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label={t('nav.closeSearch')}
                        className="text-brand-teal/50 hover:text-brand-teal shrink-0 transition-colors"
                    >
                        <X className="size-5" />
                    </button>
                </form>

                <div className="max-h-[60vh] overflow-y-auto">
                    {term === '' ? (
                        <p className="text-brand-teal/50 px-4 py-8 text-center text-sm">{t('search.prompt')}</p>
                    ) : failed ? (
                        <p className="px-4 py-8 text-center text-sm text-red-600">{t('search.failed')}</p>
                    ) : loading ? (
                        <p className="text-brand-teal/50 px-4 py-8 text-center text-sm">{t('catalogue.searching')}</p>
                    ) : results.length === 0 ? (
                        <p className="text-brand-teal/60 px-4 py-8 text-center text-sm">{t('catalogue.noResults')}</p>
                    ) : (
                        <ul ref={listRef} className="brand-scrollbar py-1">
                            {results.map((r, i) => (
                                <li key={r.slug}>
                                    <button
                                        type="button"
                                        onMouseEnter={() => setActive(i)}
                                        onClick={() => go(`/products/${r.slug}`)}
                                        className={`flex w-full items-center gap-3 px-4 py-2.5 text-start transition-colors ${
                                            i === active ? 'bg-brand-cream' : ''
                                        }`}
                                    >
                                        {r.image ? (
                                            <img src={r.image} alt="" className="size-12 shrink-0 rounded-lg object-cover" />
                                        ) : (
                                            <span className="bg-brand-cream flex size-12 shrink-0 items-center justify-center rounded-lg text-xl">
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
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {/* Hidden when the query cannot be satisfied by the results page at
                    all, rather than linking somewhere empty. */}
                {submitTerm && (
                    <button
                        type="button"
                        onClick={() => go(`/shop?q=${encodeURIComponent(submitTerm)}`)}
                        className="border-brand-gold/15 text-brand-gold hover:bg-brand-cream w-full border-t px-4 py-3 text-start text-sm font-medium transition-colors"
                    >
                        {corrected ? t('search.viewAllCorrected', { term: submitTerm }) : t('catalogue.searchViewAll')}
                    </button>
                )}
            </div>
        </div>,
        document.body,
    );
}
