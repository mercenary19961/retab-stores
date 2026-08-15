import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Slide {
    image: string;
    /** Portrait crop for phones — see the <picture> note in the markup below. */
    imageMobile: string;
    line1: string;
    line2: string;
    subtext: string;
    ctaLabel: string;
    ctaHref: string;
}

/**
 * Where the desktop landscape crop stops working and the portrait one takes over.
 * 640px (Tailwind's `sm`): the 1440x800 desktop image renders only ~217px tall at
 * 390px wide — a sliver with the headline crammed into it — while the 402x874
 * portrait renders ~848px, about one phone screen, which is what it was cut for.
 */
const MOBILE_ART = '(max-width: 639px)';

/** Rounded-triangle carousel arrow (from Polygon 2.svg). Points right by default. */
function Arrow({ flip }: { flip?: boolean }) {
    return (
        <svg width="22" height="23" viewBox="0 0 24 25" fill="none" aria-hidden className={flip ? '-scale-x-100' : undefined}>
            <path
                d="M19.9167 6.95319C24.0648 9.34813 24.0648 15.3355 19.9167 17.7304L9.33334 23.8407C5.18519 26.2356 0 23.242 0 18.4521V6.23151C0 1.44165 5.18519 -1.55203 9.33333 0.842905L19.9167 6.95319Z"
                fill="white"
            />
        </svg>
    );
}

export default function StoreHero() {
    const { t } = useTranslation();

    // TODO: slides 2–4 (images + copy) still needed — carousel controls appear
    // automatically once there is more than one slide.
    const slides: Slide[] = [
        {
            image: '/images/hero/slide-1.webp',
            imageMobile: '/images/hero/slide-1-mobile.webp',
            line1: t('hero.headlineLine1'),
            line2: t('hero.headlineLine2'),
            subtext: t('hero.subtext'),
            ctaLabel: t('hero.cta'),
            ctaHref: '/shop',
        },
    ];

    const [index, setIndex] = useState(0);
    const slide = slides[index];
    const many = slides.length > 1;
    const prev = () => setIndex((i) => (i - 1 + slides.length) % slides.length);
    const next = () => setIndex((i) => (i + 1) % slides.length);

    return (
        <section className="relative w-full overflow-hidden">
            {/* <picture>, not two <img> toggled with `hidden`: the browser picks ONE
                source and downloads only that, so a phone never pulls the 145 KB
                desktop crop it isn't going to show. */}
            <picture>
                <source media={MOBILE_ART} srcSet={slide.imageMobile} />
                <img src={slide.image} alt="" className="block h-auto w-full" />
            </picture>

            {/* Scrim direction follows where the copy sits: left-to-right on desktop
                (text on the left), top-down on phones (text in the headroom above the
                subject). Measured: the portrait crop is uniform sand to ~39% of its
                height (row sd 13-24), then sd jumps to ~49 where the man and the fire
                begin — so the copy has to stay inside that top band. */}
            <div className="pointer-events-none absolute inset-0 bg-gradient-to-r from-white/45 via-white/10 to-transparent max-sm:bg-gradient-to-b max-sm:from-white/55 max-sm:via-white/15" />

            {/* Content block — physically anchored to the left (the product sits
                baked into the right of the image), text aligned per reading dir.
                `lg:-translate-x-4` nudges the block slightly left on laptop+
                screens (≥1024px), a small enough fixed offset (16px) that it's
                harmless at any width.
                The vertical nudge is `lg:-translate-y-[6.5vw]`, NOT a fixed value
                — the image is aspect-locked (`w-full h-auto`, no max-width), so
                its rendered height scales linearly with viewport width (≈1067px
                tall at 1920px wide, but only ≈759px at 1366px). A fixed px/rem
                shift is a small fraction of the image at 1920px+ but eats a much
                bigger share of it on a narrower laptop, which clips the headline
                against the section's top edge (overflow-hidden crops rather than
                reflows). `vw` keeps the shift exactly proportional to the image's
                own height at every width, so retune by adjusting the vw number,
                not by switching back to a fixed unit. */}
            {/* Desktop: a left column beside the baked-in product. Phones: the full
                width of the top 38% band, since the portrait crop puts the subject
                centre-bottom and leaves the sky/sand above it empty. */}
            <div className="absolute inset-y-0 left-0 flex w-[56%] min-w-[300px] items-center pr-4 pl-[5%] max-sm:inset-y-auto max-sm:top-0 max-sm:h-[38%] max-sm:w-full max-sm:min-w-0 max-sm:justify-center max-sm:px-6 lg:-translate-x-4 lg:-translate-y-[6.5vw]">
                <div className="w-full text-start max-sm:text-center">
                    {/* Below 732px the kashida-elongated headline overflows the
                        narrow text column, so step the size down there and smaller.
                        line1 stays brand-teal (reads against the light sand); line2
                        is white per the two-tone treatment — see the shadow note
                        below on the subtext for why it carries a text-shadow. */}
                    {/* ⚠️ The old shrink steps here were tuned for the DESKTOP crop
                        squeezed onto a phone (217px tall at 390px wide) and are far too
                        small now that phones get a full-height portrait image, so `max-sm`
                        re-enlarges everything below 640px — the exact range where the
                        portrait art is in play.
                        🔑 There is deliberately no `max-[480px]` step any more: measured at
                        460px, the h1 computes to 34.5px, which is max-sm's value and not
                        the 32px an active max-[480px] rule would give. The named `max-sm`
                        variant is emitted AFTER both arbitrary ones (max-[732px] and
                        max-[480px]) and wins throughout, so any such rule would be dead
                        code. Below 640 there is now one size set, not three. */}
                    <h1 className="font-heading text-[clamp(2.25rem,6.2vw,5.5rem)] leading-[1.08] font-black max-[732px]:text-[clamp(1.35rem,4.5vw,2rem)] max-sm:text-[clamp(1.6rem,7.5vw,2.6rem)]">
                        <span className="text-brand-teal block">{slide.line1}</span>
                        <span className="block text-white [text-shadow:0_1px_3px_rgba(0,0,0,0.6),0_4px_16px_rgba(0,0,0,0.4)]">{slide.line2}</span>
                    </h1>

                    {/* Subtext + CTA share a shrink-to-fit column sized to the subtext;
                        the CTA is centred under it. `text-center` is direction-agnostic,
                        so it behaves identically in Arabic (RTL) and English (LTR). */}
                    <div className="mt-5 inline-block max-w-full">
                        <div className="flex items-center gap-3">
                            {/* Flanking lines — first child sits on the start side
                                (right in RTL), the last on the end side (left).
                                Left teal on purpose: they're decorative rules, not
                                text, and read fine against either headline color. */}
                            <span className="bg-brand-teal hidden h-1.5 w-[clamp(2rem,5vw,4.7rem)] shrink-0 rounded-full min-[733px]:block" />
                            {/* White per the request. The source photo is uniformly
                                light sand behind this whole column (no dark area to
                                sit white text on) and the existing scrim below
                                (`from-white/45`) was tuned to lighten the photo FOR
                                the old dark-teal text — which works against white
                                text. A two-layer text-shadow (tight dark edge + a
                                soft wide falloff) compensates without touching the
                                scrim, which line1 and the CTA still rely on. Remove
                                the [text-shadow:...] utility to see the raw contrast. */}
                            <p className="font-heading text-[clamp(0.95rem,1.9vw,1.63rem)] text-white [text-shadow:0_1px_3px_rgba(0,0,0,0.6),0_4px_16px_rgba(0,0,0,0.4)] max-[732px]:text-[0.8rem] max-sm:max-w-none max-sm:text-[0.95rem]">
                                {slide.subtext}
                            </p>
                            <span className="bg-brand-teal hidden h-1.5 w-[clamp(2rem,5vw,4.7rem)] shrink-0 rounded-full min-[733px]:block" />
                        </div>

                        <div className="mt-7 text-center">
                            <Link
                                href={slide.ctaHref}
                                className="bg-brand-teal font-heading hover:bg-brand-teal/90 inline-block rounded-full px-10 py-4 text-[clamp(1.15rem,2.6vw,2.5rem)] font-black text-white transition-colors max-[732px]:px-6 max-[732px]:py-2.5 max-[732px]:text-[0.9rem] max-sm:px-7 max-sm:py-3 max-sm:text-[1rem]"
                            >
                                <span className="cta-shimmer">{slide.ctaLabel}</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            {/* Carousel arrows (only when there's more than one slide). */}
            {many && (
                <>
                    <button
                        type="button"
                        onClick={next}
                        aria-label={t('hero.nextSlide')}
                        className="absolute top-1/2 left-4 -translate-y-1/2 opacity-70 transition-opacity hover:opacity-100"
                    >
                        <Arrow flip />
                    </button>
                    <button
                        type="button"
                        onClick={prev}
                        aria-label={t('hero.prevSlide')}
                        className="absolute top-1/2 right-4 -translate-y-1/2 opacity-70 transition-opacity hover:opacity-100"
                    >
                        <Arrow />
                    </button>

                    {/* Dots */}
                    <div className="absolute bottom-5 left-1/2 flex -translate-x-1/2 items-center gap-2">
                        {slides.map((_, i) => (
                            <button
                                key={i}
                                type="button"
                                onClick={() => setIndex(i)}
                                aria-label={`${t('hero.nextSlide')} ${i + 1}`}
                                className={`rounded-full bg-white transition-all ${
                                    i === index ? 'size-3 opacity-90' : 'size-2 opacity-50 hover:opacity-75'
                                }`}
                            />
                        ))}
                    </div>
                </>
            )}
        </section>
    );
}
