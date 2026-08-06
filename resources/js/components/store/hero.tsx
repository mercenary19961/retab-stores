import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Slide {
    image: string;
    line1: string;
    line2: string;
    subtext: string;
    ctaLabel: string;
    ctaHref: string;
}

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
            <img src={slide.image} alt="" className="block h-auto w-full" />

            {/* Soft scrim to guarantee text contrast on the left across screen sizes. */}
            <div className="pointer-events-none absolute inset-0 bg-gradient-to-r from-white/45 via-white/10 to-transparent" />

            {/* Content block — physically anchored to the left (the product sits
                baked into the right of the image), text aligned per reading dir.
                `lg:-translate-x-4 lg:-translate-y-6` nudges the whole block up and
                left on laptop+ screens (≥1024px) without touching the centering/
                positioning logic below — retune by adjusting those two values
                (Tailwind spacing scale: 4 = 1rem/16px, 6 = 1.5rem/24px). */}
            <div className="absolute inset-y-0 left-0 flex w-[56%] min-w-[300px] items-center pr-4 pl-[5%] max-sm:min-w-0 lg:-translate-x-4 lg:-translate-y-6">
                <div className="w-full text-start">
                    {/* Below 732px the kashida-elongated headline overflows the
                        narrow text column, so step the size down there and smaller.
                        line1 stays brand-teal (reads against the light sand); line2
                        is white per the two-tone treatment — see the shadow note
                        below on the subtext for why it carries a text-shadow. */}
                    <h1 className="font-heading text-[clamp(2.25rem,6.2vw,5.5rem)] leading-[1.08] font-black max-[732px]:text-[clamp(1.35rem,4.5vw,2rem)] max-[480px]:text-[clamp(0.85rem,4.5vw,1.35rem)]">
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
                            <p className="font-heading text-[clamp(0.95rem,1.9vw,1.63rem)] text-white [text-shadow:0_1px_3px_rgba(0,0,0,0.6),0_4px_16px_rgba(0,0,0,0.4)] max-[732px]:text-[0.8rem] max-[480px]:max-w-[10rem] max-[480px]:text-[0.7rem]">
                                {slide.subtext}
                            </p>
                            <span className="bg-brand-teal hidden h-1.5 w-[clamp(2rem,5vw,4.7rem)] shrink-0 rounded-full min-[733px]:block" />
                        </div>

                        <div className="mt-7 text-center max-[480px]:mt-2">
                            <Link
                                href={slide.ctaHref}
                                className="bg-brand-teal font-heading hover:bg-brand-teal/90 inline-block rounded-full px-10 py-4 text-[clamp(1.15rem,2.6vw,2.5rem)] font-black text-white transition-colors max-[732px]:px-6 max-[732px]:py-2.5 max-[732px]:text-[0.9rem] max-[480px]:px-4 max-[480px]:py-2 max-[480px]:text-[0.72rem]"
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
