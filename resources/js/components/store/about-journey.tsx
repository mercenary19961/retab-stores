import { useTranslation } from 'react-i18next';

interface Step {
    key: string;
    title: string;
    lead: string;
    body: string;
}

/**
 * About Us → "كيف بدأت رحلتنا" (Figma: "about us - our journey").
 *
 * Four photo cards, each a stage of the brand's story. The geometry is taken by
 * measuring the 1440×800 composite rather than eyeballing it, and every value is
 * expressed as a PERCENTAGE of the frame so the row keeps the designer's rhythm
 * at any width:
 *
 *   4 cards × 295px + 3 gaps × 58px + 2 margins × 43px = 1440   (exactly)
 *   →        20.49%          4.03%              2.99%
 *
 * The card box measures 295×634 in the composite (y 129→763), so the plates are
 * `object-cover`ed into a 295/634 aspect box: the supplied photos are 295×531,
 * i.e. exactly card width but shorter than the card, so anything other than cover
 * would leave a strip of empty card at the bottom.
 *
 * All four plates are the designer's own 295×531 WebP files, copied in
 * byte-for-byte rather than re-encoded — a second lossy pass over an already-lossy
 * WebP buys ~40 KB at the cost of visible quality, and 174 KB across four
 * lazy-loaded cards is not worth optimising. (The first pass shipped a flat teal
 * placeholder for `develop`, since that photo arrived later.)
 */
export default function AboutJourney() {
    const { t } = useTranslation();

    // i18next hands back the raw array for an array-valued key; the guard keeps a
    // missing or mistyped key from throwing at render.
    const raw = t('about.journey.steps', { returnObjects: true, defaultValue: [] }) as unknown;
    const steps = (Array.isArray(raw) ? raw : []) as Step[];

    return (
        <section className="relative w-full overflow-hidden bg-white py-[clamp(2rem,5vw,4.5rem)]">
            {/* Brand knot watermark (LOGO 2). Positioned PHYSICALLY top-left in both
                locales, unlike the navbar's logical `end-0`: this crop is baked to
                bleed off the top-left corner, so mirroring it for LTR would turn
                the cut edge inward and read as a clipping bug. The asset ships
                pre-faded by the designer, so it needs no opacity of its own. */}
            <img
                src="/images/about/journey-watermark.webp"
                alt=""
                aria-hidden
                className="pointer-events-none absolute top-0 left-0 w-[92%] max-w-[1321px] select-none"
                loading="lazy"
            />

            <div className="relative mx-auto w-[94.02%] max-w-[1354px]">
                {/* ⚠️ `text-start`, NOT `text-end`. These are logical properties
                    resolved against the READING direction, so `end` means left in
                    Arabic and right in English — the exact inverse of what is
                    wanted, in both locales at once. The heading must sit on the
                    side the text begins on: right in AR, left in EN. */}
                <h2 className="text-brand-teal font-heading text-start text-[clamp(1.6rem,4.7vw,4.25rem)] leading-[1.15] font-black">
                    {t('about.journey.heading')}
                </h2>

                {/* Four equal columns rather than a flex row: `grid` with a
                    percentage gap reproduces the measured 58/295 rhythm and, more
                    importantly, the columns stay equal when one card's copy runs
                    longer than the others. Collapses to two columns on small
                    screens — four 20%-wide cards on a phone would be unreadable. */}
                <ul className="mt-[clamp(1.25rem,3.4vw,3rem)] grid grid-cols-2 gap-[4.28%] sm:grid-cols-4">
                    {steps.map((step) => (
                        <li key={step.key} className="relative aspect-[295/634] overflow-hidden rounded-[clamp(1rem,3.3vw,3rem)]">
                            <img
                                src={`/images/about/journey-${step.key}.webp`}
                                alt=""
                                className="absolute inset-0 h-full w-full object-cover"
                                loading="lazy"
                            />

                            {/* Two scrims, not one. The plates are ordinary photos
                                with bright areas top and bottom, and the copy sits
                                at BOTH ends, so a single overlay dark enough for the
                                headline would flatten the middle of the image. */}
                            <div aria-hidden className="absolute inset-0 bg-gradient-to-b from-black/45 via-black/10 to-black/65" />

                            {/* `text-start` for the same reason as the heading: the
                                card copy begins on the right in Arabic and on the
                                left in English. */}
                            <div className="absolute inset-0 flex flex-col justify-between p-[clamp(0.6rem,1.5vw,1.4rem)] text-start">
                                <h3 className="font-heading text-[clamp(0.95rem,2vw,1.85rem)] leading-tight font-black text-white">{step.title}</h3>

                                <div>
                                    <p className="font-heading text-[clamp(0.85rem,1.6vw,1.5rem)] leading-tight font-black text-white">{step.lead}</p>
                                    <p className="mt-[0.35em] text-[clamp(0.7rem,1.25vw,1.15rem)] leading-[1.5] text-white/90">{step.body}</p>
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
