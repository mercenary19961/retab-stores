import { useLocalized } from '@/lib/localize';
import { Link } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

export interface CarouselProduct {
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
        // Sized in CSS rather than by the width/height attributes so it can shrink
        // on phones, where the arrows overlay the cards instead of sitting in a gutter.
        <svg viewBox="0 0 24 25" fill="none" aria-hidden className={`h-[17px] w-4 sm:h-[27px] sm:w-[26px] ${flip ? '-scale-x-100' : ''}`}>
            <path
                d="M19.9167 6.95319C24.0648 9.34813 24.0648 15.3355 19.9167 17.7304L9.33334 23.8407C5.18519 26.2356 0 23.242 0 18.4521V6.23151C0 1.44165 5.18519 -1.55203 9.33333 0.842905L19.9167 6.95319Z"
                fill="#1b4e53"
            />
        </svg>
    );
}

/**
 * Shared homepage product carousel (Best Sellers / New Arrivals). Shows whole
 * cards only (4 on desktop / 3 on tablet / 2 on mobile), never a partial peek;
 * card widths exactly fill the track so a page-scroll always lands on a fresh
 * full set. Arrows sit in side gutters, centred on the card image, and a faint
 * watermark anchors the start-bottom. Pass `badgeLabel` to show a gold corner
 * badge (e.g. "صنف جديد") on every card.
 *
 * Physical-pixel scrolling works in both LTR and RTL: modern browsers keep
 * scrollLeft 0 at the start and grow it negative toward the end in RTL, so a
 * left chevron always means "reveal content to the left" (negative) either way.
 */
export default function ProductCarousel({
    title,
    products,
    badgeLabel,
    mirrorPattern = false,
}: {
    title: string;
    products: CarouselProduct[];
    badgeLabel?: string;
    /** Flip the corner watermark to the right edge (mirrored) instead of the left. */
    mirrorPattern?: boolean;
}) {
    const { t, i18n } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');
    const trackRef = useRef<HTMLDivElement>(null);
    // Kept as PHYSICAL directions ("is there anything further left?"), because the
    // buttons are physical. Deriving them from a start/end pair silently inverts
    // them under RTL — see measure().
    const [edges, setEdges] = useState({ canLeft: false, canRight: true });
    // Whether the track actually overflows at the current breakpoint — drives
    // both the arrows (hidden when everything fits) and centring (few cards are
    // centred instead of hugging the start). Default heuristic: more cards than
    // the widest view shows (4); measure() corrects it to the real overflow on
    // mount / resize, so tablet (3) and mobile (2) are handled too.
    const [scrollable, setScrollable] = useState(() => products.length > 4);
    const [imageHeight, setImageHeight] = useState(0);

    const measure = useCallback(() => {
        const el = trackRef.current;
        if (!el) return;
        const max = el.scrollWidth - el.clientWidth;
        // scrollLeft runs 0..max in LTR but -max..0 in RTL (modern browsers keep
        // 0 at the reading start and go negative toward the end), so the travel
        // limits have to be read per direction. Collapsing them with Math.abs()
        // makes "at the start" mean the LEFT edge in LTR and the RIGHT edge in
        // RTL, which inverted both buttons on the Arabic site: the arrow that
        // actually paged was the disabled one, and the enabled one clamped and
        // did nothing.
        const rtl = getComputedStyle(el).direction === 'rtl';
        const pos = el.scrollLeft;
        setEdges({
            canLeft: pos > (rtl ? -max : 0) + 1,
            canRight: pos < (rtl ? 0 : max) - 1,
        });
        setScrollable(max > 1);

        // Height of the first card's square image (its first child), so arrows
        // sit at its centre — not the centre of the taller card incl. name/price.
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
        // `i18n.language`: the locale toggle flips document.dir without a reload or a
        // resize, so nothing else would re-run measure() and the arrows would keep
        // the previous direction's enabled states until the next scroll.
    }, [measure, products.length, i18n.language]);

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
    // Below `sm` there is no gutter to sit in — the track is full-bleed so the two
    // cards stay readable — so the arrows are pulled out into the page's own side
    // margin (`-left-5`/`-right-5`), clearing all but ~6px of the card rather than
    // sitting on top of the product photo. The disc covers that sliver, which can
    // fall on a dark image. Without any arrow the track is swipeable but gives no
    // sign that anything follows the two visible cards.
    const arrowBase =
        'absolute z-10 -translate-y-1/2 p-2 transition-opacity max-sm:rounded-full max-sm:bg-white/85 max-sm:p-1.5 max-sm:shadow-md max-sm:backdrop-blur-sm';

    return (
        <section className="relative w-full overflow-hidden bg-white py-10 sm:py-14">
            {/* Faint flowing-lines watermark (Asset 3), bottom corner. Mirrored to
                the right edge when `mirrorPattern` is set (e.g. the Offers strip). */}
            <img
                src="/images/best-sellers/pattern.png"
                alt=""
                aria-hidden
                className={`pointer-events-none absolute bottom-0 h-full w-auto opacity-70 select-none ${
                    mirrorPattern ? 'right-0 -scale-x-100' : 'left-0'
                }`}
            />

            <div className="relative mx-auto max-w-[1600px] px-6 lg:px-12">
                <h2 className="font-heading text-brand-teal mb-8 text-center text-[clamp(1.75rem,4vw,2.75rem)] font-black">{title}</h2>

                <div className="relative">
                    {/* Prev (left) arrow — sits in the left gutter, clear of the cards.
                        Only shown when the track overflows (few cards → no arrows). */}
                    {scrollable && (
                        <button
                            type="button"
                            onClick={() => page('left')}
                            aria-label={t('carousel.prev')}
                            style={{ top: arrowTop }}
                            className={`${arrowBase} left-0 max-sm:-left-5 ${arrowTop === undefined ? 'top-1/2' : ''} ${
                                edges.canLeft ? 'opacity-70 hover:opacity-100' : 'pointer-events-none opacity-20'
                            }`}
                        >
                            <Arrow flip />
                        </button>
                    )}

                    {/* Track: full-bleed on mobile (swipe), inset by gutters on ≥sm so
                        the arrows have room. Cards exactly fill it — no partial peek. */}
                    <div
                        ref={trackRef}
                        className={`flex snap-x snap-mandatory gap-6 overflow-x-auto scroll-smooth [scrollbar-width:none] sm:mx-14 lg:mx-16 [&::-webkit-scrollbar]:hidden ${
                            scrollable ? '' : 'justify-center'
                        }`}
                    >
                        {products.map((p) => (
                            <Link
                                key={p.id}
                                href={`/products/${p.slug}`}
                                className="group relative shrink-0 basis-[calc((100%_-_1.5rem)_/_2)] snap-start md:basis-[calc((100%_-_3rem)_/_3)] lg:basis-[calc((100%_-_4.5rem)_/_4)]"
                            >
                                {p.image ? (
                                    <img
                                        src={p.image}
                                        alt={localized(p, 'name')}
                                        className="aspect-square w-full rounded-[23%] object-cover shadow-sm transition group-hover:shadow-md"
                                    />
                                ) : (
                                    <div className="bg-brand-cream flex aspect-square w-full items-center justify-center rounded-[23%] text-5xl shadow-sm">
                                        🌴
                                    </div>
                                )}

                                {badgeLabel && (
                                    <span className="bg-brand-gold font-heading absolute top-3 left-3 z-10 rounded-full px-3 py-1 text-xs font-bold text-white shadow-sm">
                                        {badgeLabel}
                                    </span>
                                )}

                                {/* Two lines, ALWAYS two lines' worth of box. `line-clamp-2`
                                    alone caps the overflow but still lets a short name
                                    occupy one line, which drops that card's price a line
                                    higher than its neighbour's. Reserving the space with
                                    `2lh` (two line-heights, so it follows the clamped font
                                    size automatically) is what actually lines the row up. */}
                                <h3 className="font-heading text-brand-gold mt-4 line-clamp-2 min-h-[2lh] text-center text-[clamp(1rem,2vw,1.35rem)]">
                                    {localized(p, 'name')}
                                </h3>
                                <div className="font-heading text-brand-teal mt-1 text-center">
                                    {p.on_sale ? (
                                        // Stacked below `sm`, side by side above. Two prices
                                        // do not fit one line on a ~160px phone card, and the
                                        // `nowrap` is what stops an amount splitting from its
                                        // currency ("100.00" over "SAR").
                                        <span className="inline-flex flex-col items-center gap-0 sm:flex-row sm:gap-2">
                                            <span className="font-bold whitespace-nowrap">
                                                {p.effective_price.toFixed(2)} {currency}
                                            </span>
                                            <span className="text-brand-teal/50 text-sm whitespace-nowrap line-through">
                                                {p.price.toFixed(2)} {currency}
                                            </span>
                                        </span>
                                    ) : (
                                        <span className="font-bold whitespace-nowrap">
                                            {p.price.toFixed(2)} {currency}
                                        </span>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>

                    {/* Next (right) arrow. */}
                    {scrollable && (
                        <button
                            type="button"
                            onClick={() => page('right')}
                            aria-label={t('carousel.next')}
                            style={{ top: arrowTop }}
                            className={`${arrowBase} right-0 max-sm:-right-5 ${arrowTop === undefined ? 'top-1/2' : ''} ${
                                edges.canRight ? 'opacity-70 hover:opacity-100' : 'pointer-events-none opacity-20'
                            }`}
                        >
                            <Arrow />
                        </button>
                    )}
                </div>
            </div>
        </section>
    );
}
