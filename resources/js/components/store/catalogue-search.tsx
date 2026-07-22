import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocalized } from '@/lib/localize';

interface Suggestion {
    slug: string;
    name_ar: string;
    name_en: string | null;
    image: string | null;
    price: number;
    effective_price: number;
    on_sale: boolean;
    coming_soon: boolean;
}

/**
 * Catalogue search with a live typeahead: as the customer types (debounced), a
 * dropdown of matching products (thumbnail + name + price) appears instantly.
 * Clicking a suggestion opens that product; pressing Enter runs the full grid
 * filter via `onSubmit`. Closes on outside-click / Escape.
 */
export default function CatalogueSearch({ initialQuery, onSubmit }: { initialQuery: string; onSubmit: (q: string) => void }) {
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');

    const [q, setQ] = useState(initialQuery);
    const [results, setResults] = useState<Suggestion[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const boxRef = useRef<HTMLDivElement>(null);

    // Debounced live fetch. <2 chars clears the dropdown; each keystroke aborts
    // the in-flight request so only the latest result set ever lands.
    useEffect(() => {
        const term = q.trim();
        if (term.length < 2) {
            setResults([]);
            setOpen(false);
            setLoading(false);
            return;
        }

        setLoading(true);
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(`/shop/search?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((r) => r.json())
                .then((d: { results: Suggestion[] }) => {
                    setResults(d.results);
                    setOpen(true);
                    setLoading(false);
                })
                .catch(() => {
                    /* aborted or network error — keep the last results */
                });
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [q]);

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

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setOpen(false);
        onSubmit(q.trim());
    };

    return (
        <div ref={boxRef} className="relative min-w-0 flex-1 sm:max-w-xs">
            <form onSubmit={submit}>
                <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-brand-gold" />
                <input
                    type="search"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    onFocus={() => results.length > 0 && setOpen(true)}
                    placeholder={t('catalogue.searchPlaceholder')}
                    aria-label={t('nav.search')}
                    autoComplete="off"
                    className="w-full rounded-full border border-brand-gold/30 bg-white py-2 ps-9 pe-4 text-sm text-brand-teal placeholder:text-brand-teal/40 focus:border-brand-gold focus:outline-none focus:ring-2 focus:ring-brand-gold/25"
                />
            </form>

            {open && (
                <div className="absolute z-30 mt-2 w-full overflow-hidden rounded-2xl border border-brand-gold/20 bg-white shadow-lg sm:min-w-[22rem]">
                    {results.length === 0 ? (
                        <p className="px-4 py-3 text-sm text-brand-teal/60">
                            {loading ? t('catalogue.searching') : t('catalogue.noResults')}
                        </p>
                    ) : (
                        <ul className="brand-scrollbar max-h-96 overflow-y-auto py-1">
                            {results.map((r) => (
                                <li key={r.slug}>
                                    <Link
                                        href={`/products/${r.slug}`}
                                        onClick={() => setOpen(false)}
                                        className="flex items-center gap-3 px-3 py-2 transition-colors hover:bg-brand-cream"
                                    >
                                        {r.image ? (
                                            <img src={r.image} alt="" className="size-11 shrink-0 rounded-lg object-cover" />
                                        ) : (
                                            <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-brand-cream text-lg">🌴</span>
                                        )}
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium text-brand-teal">{localized(r, 'name')}</span>
                                            {r.coming_soon ? (
                                                <span className="text-xs font-semibold text-brand-gold">{t('catalogue.comingSoon')}</span>
                                            ) : (
                                                <span className="text-xs text-brand-teal/70">
                                                    {r.effective_price.toFixed(2)} {currency}
                                                    {r.on_sale && <span className="ms-1.5 text-brand-teal/40 line-through">{r.price.toFixed(2)}</span>}
                                                </span>
                                            )}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                            <li className="border-t border-brand-gold/10">
                                <button
                                    type="button"
                                    onClick={submit}
                                    className="w-full px-4 py-2.5 text-start text-sm font-medium text-brand-gold transition-colors hover:bg-brand-cream"
                                >
                                    {t('catalogue.searchViewAll')}
                                </button>
                            </li>
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
