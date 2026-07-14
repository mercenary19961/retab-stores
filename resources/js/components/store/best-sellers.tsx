import { Link } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocalized } from '@/lib/localize';

interface ProductCard {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    price: number;
    sale_price: number | null;
    effective_price: number;
    on_sale: boolean;
    is_featured: boolean;
    image: string | null;
    category: { name_ar: string; name_en: string | null; slug: string } | null;
}

/** Rounded-triangle arrow (from Polygon 2.svg), teal. Points right by default. */
function Arrow({ flip }: { flip?: boolean }) {
    return (
        <svg
            width="26"
            height="27"
            viewBox="0 0 24 25"
            fill="none"
            aria-hidden
            className={flip ? '-scale-x-100' : undefined}
        >
            <path
                d="M19.9167 6.95319C24.0648 9.34813 24.0648 15.3355 19.9167 17.7304L9.33334 23.8407C5.18519 26.2356 0 23.242 0 18.4521V6.23151C0 1.44165 5.18519 -1.55203 9.33333 0.842905L19.9167 6.95319Z"
                fill="#1b4e53"
            />
        </svg>
    );
}

/**
 * "الأكثر مبيعاً" homepage strip — a horizontal, scroll-snap product carousel.
 * Arrows show on ≥sm (mobile swipes). Physical-pixel edge detection works in
 * both LTR and RTL: modern browsers keep scrollLeft 0 at the start and grow it
 * negative toward the end in RTL, so |scrollLeft| is distance-from-start either way.
 */
export default function BestSellers({ products }: { products: ProductCard[] }) {
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');
    const trackRef = useRef<HTMLDivElement>(null);
    const [edges, setEdges] = useState({ atStart: true, atEnd: false });

    const updateEdges = useCallback(() => {
        const el = trackRef.current;
        if (!el) return;
        const max = el.scrollWidth - el.clientWidth;
        const pos = Math.abs(el.scrollLeft);
        setEdges({ atStart: pos <= 1, atEnd: pos >= max - 1 });
    }, []);

    useEffect(() => {
        updateEdges();
        const el = trackRef.current;
        if (!el) return;
        el.addEventListener('scroll', updateEdges, { passive: true });
        window.addEventListener('resize', updateEdges);
        return () => {
            el.removeEventListener('scroll', updateEdges);
            window.removeEventListener('resize', updateEdges);
        };
    }, [updateEdges, products.length]);

    const scroll = (dir: 'left' | 'right') => {
        const el = trackRef.current;
        if (!el) return;
        const card = el.firstElementChild as HTMLElement | null;
        const step = card ? card.offsetWidth + 16 : el.clientWidth * 0.8;
        // Left chevron reveals content to the left (negative), right the opposite —
        // consistent physically across LTR/RTL in spec-compliant browsers.
        el.scrollBy({ left: dir === 'left' ? -step : step, behavior: 'smooth' });
    };

    if (products.length === 0) return null;

    return (
        <section className="w-full bg-white py-10 sm:py-14">
            <div className="mx-auto max-w-6xl px-4">
                <h2 className="mb-8 text-center font-heading font-black text-brand-teal text-[clamp(1.75rem,4vw,2.75rem)]">
                    {t('bestSellers.title')}
                </h2>

                <div className="relative">
                    {/* Prev (left) arrow — hidden on touch, which swipes instead. */}
                    <button
                        type="button"
                        onClick={() => scroll('left')}
                        aria-label={t('bestSellers.prev')}
                        className={`absolute top-1/2 -left-2 z-10 hidden -translate-y-1/2 p-2 transition-opacity sm:-left-6 sm:block ${
                            edges.atStart ? 'pointer-events-none opacity-20' : 'opacity-70 hover:opacity-100'
                        }`}
                    >
                        <Arrow flip />
                    </button>

                    <div
                        ref={trackRef}
                        className="flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    >
                        {products.map((p) => (
                            <Link
                                key={p.id}
                                href={`/products/${p.slug}`}
                                className="group shrink-0 snap-start basis-[46%] sm:basis-[31%] lg:basis-[22.5%]"
                            >
                                {p.image ? (
                                    <img
                                        src={p.image}
                                        alt={localized(p, 'name')}
                                        className="aspect-square w-full rounded-[1.75rem] object-cover shadow-sm transition group-hover:shadow-md"
                                    />
                                ) : (
                                    <div className="flex aspect-square w-full items-center justify-center rounded-[1.75rem] bg-brand-cream text-5xl shadow-sm">
                                        🌴
                                    </div>
                                )}
                                <h3 className="mt-4 text-center font-heading text-brand-gold text-[clamp(1rem,2vw,1.35rem)]">
                                    {localized(p, 'name')}
                                </h3>
                                <div className="mt-1 text-center font-heading text-brand-teal">
                                    {p.on_sale ? (
                                        <span className="inline-flex items-center gap-2">
                                            <span className="font-bold">
                                                {p.effective_price} {currency}
                                            </span>
                                            <span className="text-sm text-brand-teal/50 line-through">
                                                {p.price} {currency}
                                            </span>
                                        </span>
                                    ) : (
                                        <span className="font-bold">
                                            {p.price} {currency}
                                        </span>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>

                    {/* Next (right) arrow. */}
                    <button
                        type="button"
                        onClick={() => scroll('right')}
                        aria-label={t('bestSellers.next')}
                        className={`absolute top-1/2 -right-2 z-10 hidden -translate-y-1/2 p-2 transition-opacity sm:-right-6 sm:block ${
                            edges.atEnd ? 'pointer-events-none opacity-20' : 'opacity-70 hover:opacity-100'
                        }`}
                    >
                        <Arrow />
                    </button>
                </div>
            </div>
        </section>
    );
}
