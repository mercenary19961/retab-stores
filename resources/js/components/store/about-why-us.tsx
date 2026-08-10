import { useTranslation } from 'react-i18next';

interface Reason {
    key: string;
    title: string;
    lines: string[];
}

/**
 * About Us → "ليش رطاب" (Figma: "about us - why us").
 *
 * Four glass cards on the designer's dark teal plate. This composite arrived as a
 * PNG with no SVG, so every number below was read out of pixels. Two techniques
 * did the work and are worth reusing:
 *
 *  1. The designer also supplied the bare background, so **diffing the composite
 *     against it** isolates everything drawn on top. In the gap between the cards
 *     the two agree to 0.17/255, which proves the plate is the real background and
 *     not a lookalike; the same diff then showed the knot watermark is NOT baked
 *     into it (bottom-left pixels are lighter in the composite) and measured the
 *     card's glass overlay directly, at the same pixel, with the gradient cancelled.
 *  2. Card edges came from the **border spikes** on scan lines chosen to sit inside
 *     a card but clear of its type. Generic step-detection kept locking onto the
 *     watermark and the glyphs instead.
 *
 * Horizontal geometry, and it is symmetric to the pixel:
 *   margin 69 + card 569 + gap 165 + card 569 + margin 70 = 1442 (≈1440)
 *   card centres 353 / 1086, i.e. ±366.5 from the centre
 *
 * Unlike the values section, the design's numbers ARE usable as a grid here,
 * because each card contains its own text rather than sitting inside a column
 * beside it — so the container is simply 1303/1440 with a 165px gap.
 *
 * 🔑 The frame is 1136px tall and has to fit a viewport, so as with the values
 * section the type is scaled to 0.85 and the whitespace roughly halved (~790px).
 * Here the cards dominate the height rather than the padding, so the cards
 * necessarily end up flatter than the design's 1.635:1 — accepted deliberately: a
 * text card reads fine wider, and the alternative (scaling width to match) would
 * have left a 315px gutter between two narrow cards.
 */
export default function AboutWhyUs() {
    const { t } = useTranslation();

    // i18next hands back the raw array for an array-valued key; the guard keeps a
    // missing or mistyped key from throwing at render.
    const raw = t('about.whyUs.items', { returnObjects: true, defaultValue: [] }) as unknown;
    const reasons = (Array.isArray(raw) ? raw : []) as Reason[];

    return (
        /* ⚠️ The teal fallback is not decoration. Everything in this section is
           white or gold on the assumption of a dark plate, so if the background
           image ever fails the whole section becomes invisible against the white
           page above it. The colour is sampled from the plate itself. */
        <section className="relative w-full overflow-hidden bg-[#013E46] pt-[clamp(1rem,2.92vw,2.625rem)] pb-[clamp(1.25rem,3.61vw,3.25rem)]">
            {/* The plate is a smooth teal gradient, so `object-cover` can crop it
                freely without anything misaligning. Converted from a 1.27 MB PNG to
                an 8 KB WebP — verified against the master at mean 0.724/255 with no
                pixel off by more than 3, so the saving is PNG's poor handling of
                smooth gradients rather than a quality loss.

                ⚠️ NO negative z-index on these two images. `-z-10`/`-z-20` was the
                first attempt and it rendered the section as bare cream: `position:
                relative` alone does not create a stacking context, so a negatively
                stacked child is painted behind the nearest ANCESTOR background
                rather than merely behind its siblings. Plain `absolute` plus a
                `relative` content wrapper is enough — positioned elements with
                `z-index: auto` paint in DOM order, and the content comes last. */}
            <img src="/images/about/why-bg.webp" alt="" aria-hidden className="absolute inset-0 h-full w-full object-cover" />

            {/* Brand knot, physically bottom-left in both locales: the crop is baked
                to bleed off that corner, so mirroring it under LTR would turn the
                cut edge inward and read as a clipping bug (same reasoning as the
                journey section).

                The asset is pre-faded by the designer to exactly 7.9% (every shape
                pixel sits at alpha 117 of 127), but the composite renders it at a
                cream alpha of 5.7% — so it still needs ~0.72 on top. Two
                independent measurements agree on that figure: 5.7/7.9 = 0.72 from
                the assets, and a rendered-vs-design brightness lift of +12 against
                +8.8 = 0.73.

                ⚠️ Width is 42%, not the 52% first tried. The watermark's aspect is
                fixed while the section was compressed vertically, so sizing it off
                the WIDTH made it fill a shorter section and read as a graphic
                element rather than a texture. 42% keeps the design's horizontal
                extent (~600px at 1440) while leaving its top edge inside the
                section, so the artwork is never cut off mid-stroke. */}
            <img
                src="/images/about/why-watermark.webp"
                alt=""
                aria-hidden
                className="pointer-events-none absolute bottom-0 left-0 w-[42%] max-w-[605px] opacity-[0.72] select-none"
                loading="lazy"
            />

            <div className="relative mx-auto w-[90.49%] max-w-[1303px]">
                <h2 className="text-brand-gold font-heading text-center text-[clamp(1.9rem,6.49vw,5.85rem)] leading-[1.15] font-black">
                    {t('about.whyUs.heading')}
                </h2>

                {/* 165/1303 of the container. One column below `sm`: four cards side
                    by side is unreadable on a phone, and unlike the values pills a
                    full-width card is a perfectly normal shape. */}
                <ul className="mt-[clamp(1rem,3.33vw,3rem)] grid grid-cols-1 gap-x-[12.66%] gap-y-[clamp(1rem,2.36vw,2.125rem)] sm:grid-cols-2">
                    {reasons.map((reason) => (
                        <li
                            key={reason.key}
                            /* Border and fill are both measured, not guessed. The
                               border resolves to roughly white at 22-25% over the
                               plate. The fill is almost nothing: comparing the
                               composite to the bare plate pixel-for-pixel gives a
                               median implied white alpha of 0.023, rising from ~0 at
                               each card's top edge to ~4% at its bottom — hence a
                               gradient rather than a flat tint. No backdrop-blur:
                               the plate is a smooth gradient, so blurring it would
                               produce no visible difference for real cost. */
                            className="flex flex-col items-center rounded-[clamp(0.75rem,1.67vw,1.5rem)] border border-white/25 bg-gradient-to-b from-white/0 to-white/[0.04] pt-[clamp(1.1rem,4.17vw,3.75rem)] pb-[clamp(0.9rem,3.13vw,2.8rem)] text-center"
                        >
                            {/* Title and body are the SAME size — that is what the
                                composite measures, with weight and colour carrying
                                the hierarchy rather than scale. (Only two lines in
                                the whole design lack kashida, and those are the only
                                ones whose width and height agree on a size; both
                                land at ~40px.) */}
                            <h3 className="font-heading text-[clamp(1rem,2.36vw,2.125rem)] leading-[1.4] font-black text-white">{reason.title}</h3>

                            <p className="text-brand-gold text-[clamp(1rem,2.36vw,2.125rem)] leading-[1.4] font-normal">
                                {reason.lines.map((line, i) => (
                                    <span key={i} className="block">
                                        {line}
                                    </span>
                                ))}
                            </p>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
