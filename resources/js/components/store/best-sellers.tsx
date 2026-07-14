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

// Track gap (Tailwind gap-6 = 1.5rem = 24px). Kept in sync with the card basis
// calc()s below and the page-scroll step.
const GAP = 24;

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
 * "الأكثر مبيعاً" homepage strip — a paged product carousel showing whole cards
 * only (4 on desktop / 3 on tablet / 2 on mobile), never a partial peek. Card
 * widths exactly fill the track, so a page-scroll of one viewport always lands
 * on a fresh set of full cards. The track is inset with side gutters so the
 * arrows sit clear of the cards, and arrows are vertically centred on the image.
 *
 * Physical-pixel scrolling works in both LTR and RTL: modern browsers keep
 * scrollLeft 0 at the start and grow it negative toward the end in RTL, so a
 * left chevron always means "reveal content to the left" (negative) either way.
 */
export default function BestSellers({ products }: { products: ProductCard[] }) {
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');
    const trackRef = useRef<HTMLDivElement>(null);
    const [edges, setEdges] = useState({ atStart: true, atEnd: false });
    const [imageHeight, setImageHeight] = useState(0);

    const measure = useCallback(() => {
        const el = trackRef.current;
        if (!el) return;
        const max = el.scrollWidth - el.clientWidth;
        const pos = Math.abs(el.scrollLeft);
        setEdges({ atStart: pos <= 1, atEnd: pos >= max - 1 });

        // Height of the first card's square image, so arrows sit at its centre
        // (not the centre of the taller card that includes name + price).
        const media = el.firstElementChild?.firstElementChild as HTMLElement | undefined;
        if (media) setImageHeight(media.offsetHeight);
    }, []);

    useEffect(() => {
        measure();
        const el = trackRef.current;
        if (!el) return;
        el.addEventListener('scroll', measure, { passive: true });
        window.addEventListener('resize', measure);
        return () => {
            el.removeEventListener('scroll', measure);
            window.removeEventListener('resize', measure);
        };
    }, [measure, products.length]);

    const page = (dir: 'left' | 'right') => {
        const el = trackRef.current;
        if (!el) return;
        // One viewport of cards. Because cards exactly fill the track, clientWidth
        // + one gap equals a whole number of card steps, so the scroll snaps onto
        // a fresh full set regardless of how many are visible at this breakpoint.
        const step = el.clientWidth + GAP;
        el.scrollBy({ left: dir === 'left' ? -step : step, behavior: 'smooth' });
    };

    if (products.length === 0) return null;

    // Arrows centred on the image once measured; mid-track before then.
    const arrowTop = imageHeight ? imageHeight / 2 : undefined;
    const arrowBase =
        'absolute z-10 hidden -translate-y-1/2 p-2 transition-opacity sm:block';

    return (
        <section className="relative w-full overflow-hidden bg-white py-10 sm:py-14">
            {/* Faint flowing-lines watermark (Asset 3), anchored to the start edge. */}
            <img
                src="/images/best-sellers/pattern.png"
                alt=""
                aria-hidden
                className="pointer-events-none absolute bottom-0 left-0 h-full w-auto select-none opacity-70"
            />

            <div className="relative mx-auto max-w-[1600px] px-6 lg:px-12">
                <h2 className="mb-8 text-center font-heading font-black text-brand-teal text-[clamp(1.75rem,4vw,2.75rem)]">
                    {t('bestSellers.title')}
                </h2>

                <div className="relative">
                    {/* Prev (left) arrow — sits in the left gutter, clear of the cards. */}
                    <button
                        type="button"
                        onClick={() => page('left')}
                        aria-label={t('bestSellers.prev')}
                        style={{ top: arrowTop }}
                        className={`${arrowBase} left-0 ${arrowTop === undefined ? 'top-1/2' : ''} ${
                            edges.atStart ? 'pointer-events-none opacity-20' : 'opacity-70 hover:opacity-100'
                        }`}
                    >
                        <Arrow flip />
                    </button>

                    {/* Track: full-bleed on mobile (swipe), inset by gutters on ≥sm so
                        the arrows have room. Cards exactly fill it — no partial peek. */}
                    <div
                        ref={trackRef}
                        className="flex snap-x snap-mandatory gap-6 overflow-x-auto scroll-smooth sm:mx-14 lg:mx-16 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    >
                        {products.map((p) => (
                            <Link
                                key={p.id}
                                href={`/products/${p.slug}`}
                                className="group shrink-0 snap-start basis-[calc((100%_-_1.5rem)_/_2)] md:basis-[calc((100%_-_3rem)_/_3)] lg:basis-[calc((100%_-_4.5rem)_/_4)]"
                            >
                                {p.image ? (
                                    <img
                                        src={p.image}
                                        alt={localized(p, 'name')}
                                        className="aspect-square w-full rounded-[23%] object-cover shadow-sm transition group-hover:shadow-md"
                                    />
                                ) : (
                                    <div className="flex aspect-square w-full items-center justify-center rounded-[23%] bg-brand-cream text-5xl shadow-sm">
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
                                                {p.effective_price.toFixed(2)} {currency}
                                            </span>
                                            <span className="text-sm text-brand-teal/50 line-through">
                                                {p.price.toFixed(2)} {currency}
                                            </span>
                                        </span>
                                    ) : (
                                        <span className="font-bold">
                                            {p.price.toFixed(2)} {currency}
                                        </span>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>

                    {/* Next (right) arrow. */}
                    <button
                        type="button"
                        onClick={() => page('right')}
                        aria-label={t('bestSellers.next')}
                        style={{ top: arrowTop }}
                        className={`${arrowBase} right-0 ${arrowTop === undefined ? 'top-1/2' : ''} ${
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
