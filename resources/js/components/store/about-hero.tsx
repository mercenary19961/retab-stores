import { useTranslation } from 'react-i18next';

/**
 * About Us hero — the banner headline and the "من نحن" card over ONE photograph
 * (Figma: "about us - banner" + "about us - who we are", supplied merged).
 *
 * 🔑 These were two sections with two background images, and the join between
 * them read as a visible break: they were different photographs, so their edges
 * shared neither tone nor grain (measured at the seam, the average colour
 * differed by 22/29/10/23 across four horizontal bands — an UNEVEN gap, which is
 * the signature of different sources rather than a scale mismatch). That was
 * papered over with a cross-fade: the lower image was bled upwards and masked
 * transparent→opaque so real pixels from both photos blended.
 *
 * The designer then re-shot the lower half as a continuation of the same frame
 * and supplied one 1440×1600 image. Verified before adopting it: the new top
 * 800px differs from the old banner by a mean of 2.30/255 (i.e. identical, just
 * re-encode noise) while the bottom 800px differs by 24.09 (genuinely new). So
 * the fix is at source and the cross-fade is deleted rather than tuned — there
 * is no longer a seam to hide, because there is no longer a join.
 *
 * ⚠️ Note the constraint REVERSED. The old lower section could not be
 * `overflow-hidden` or its upward bleed was clipped and the hard line came back.
 * This one MUST be, because the banner's teal band is bled sideways by 100vw.
 */
export default function AboutHero() {
    const { t } = useTranslation();

    // i18next returns the raw array for a key whose value is an array; the guard
    // keeps a mistyped or missing key from throwing at render.
    const raw = t('about.whoWeAre.paragraphs', { returnObjects: true, defaultValue: [] }) as unknown;
    const paragraphs = Array.isArray(raw) ? (raw as string[]) : [];

    return (
        <section className="relative w-full overflow-hidden">
            {/* Full-bleed background. `object-cover` is safe here in a way it was
                not before: with a single section there is no second image whose
                scaling could disagree with this one, so a crop can never produce
                a misaligned join. It also can never leave a gap — the previous
                lower section rendered its <img> at the intrinsic aspect ratio and
                left the bottom of the section with no background at all on
                mobile, which desktop happened to hide.

                The crop direction differs by viewport, and both are wanted:
                desktop is slightly shorter than the photo's 1:1.111 aspect so it
                trims ground off the bottom, while mobile is taller so it scales
                by height and trims the SIDES, which zooms the composition and
                makes the dates box read at 390px instead of shrinking to a
                quarter size. */}
            <img
                src="/images/about/hero.webp"
                alt=""
                width={1440}
                height={1600}
                className="absolute inset-0 h-full w-full object-cover object-top"
                fetchPriority="high"
            />

            <div className="relative">
                {/* The banner copy occupies the photo's top half. 800 of the
                    1440-wide frame ≈ 55.56vw, so the headline holds its position
                    against the artwork at every width — the same reason the type
                    below is sized in vw rather than rem. */}
                <div className="relative min-h-[55.56vw]">
                    {/* Width is the Figma's teal rectangle: 727 of 1440 ≈ 50.5%.
                        That number is doing real work — the dates box starts at
                        about 52% of the photo, so a wider column drops the
                        headline on top of it.

                        ⚠️ Absolutely positioned at `left-0`, NOT laid out in
                        flow. A flow child would be placed by the reading
                        direction and jump to the RIGHT in Arabic; the dates box
                        is baked into the right of the photo, so the copy must be
                        anchored PHYSICALLY left in both locales. The wrapper's
                        min-height is what reserves the space instead. */}
                    <div className="absolute inset-y-0 left-0 flex w-[50.5%] min-w-[240px] items-center pl-[5%] max-sm:min-w-0">
                        <h1 className="font-heading w-full text-start text-[clamp(1.35rem,4.6vw,5.5rem)] leading-[1.18] font-black max-[732px]:text-[clamp(0.95rem,3.6vw,1.5rem)]">
                            {/* Same start inset as the band's text below, so both
                                lines align on the edge they are aligned to. */}
                            <span className="text-brand-teal block ps-[1.4vw]">{t('about.banner.line1')}</span>

                            {/* Teal band. In the Figma its rectangle overshoots
                                its own frame to sit flush against the banner's
                                left edge, so it is bled rather than boxed:
                                -ml/pl of 100vw stretches the background leftwards
                                without moving the text, and the section's
                                overflow-hidden clips the excess. */}
                            <span className="bg-brand-teal mt-[1.2vw] -ml-[100vw] block py-[clamp(0.35rem,1.85vw,2.2rem)] pl-[100vw] text-white">
                                {/* `ps-`, not `pe-`: text-start puts the text on
                                    the START side, so the start side is the one
                                    that needs the inset.

                                    ⚠️ line2 is bumped ~1.15x above line1/the h1
                                    base, in BOTH locales — "إلى مائدتكم" and "to
                                    your table" alike. (First scoped to `rtl:`
                                    only per the initial ask, then widened to both
                                    per follow-up.) Both the base and the <732px
                                    override are scaled by the same factor so the
                                    curve stays smooth across breakpoints rather
                                    than jumping at the mobile cutoff. */}
                                <span className="block ps-[1.4vw] text-[clamp(1.55rem,5.29vw,6.325rem)] max-[732px]:text-[clamp(1.09rem,4.14vw,1.725rem)]">
                                    {t('about.banner.line2')}
                                </span>
                            </span>
                        </h1>
                    </div>
                </div>

                {/* "من نحن" card, sitting over the ground half of the photo.
                    Deliberately NOT aspect-locked: it is text and has to be free
                    to grow. 1190 of a 1440 frame ≈ 82.6%, capped at the artwork's
                    own width so it never outgrows the design on wide screens.

                    Figma's Rectangle 24 (#FFF8F8 at 11%, 66px radius) plus the
                    background blur Figma applies as an EFFECT — effects are not
                    carried in an SVG export, so it is reproduced with
                    backdrop-blur. */}
                <div className="py-[clamp(1.75rem,5.2vw,5rem)]">
                    <div className="mx-auto w-[82.6%] max-w-[1190px] rounded-[clamp(1.25rem,4.6vw,66px)] bg-[#FFF8F8]/[0.11] pt-[clamp(0.6rem,1.4vw,1.3rem)] pb-[clamp(1.5rem,4vw,3.5rem)] ring-1 ring-white/25 backdrop-blur-md">
                        {/* The badge is tucked into the card's top corner in the
                            design, so it sits nearly flush while the copy below
                            is properly inset. Positioned logically (`ps-`), not
                            physically: nothing in the photo anchors it, so it
                            follows the reading direction and moves to the left in
                            English. */}
                        <div className="ps-[0.6vw] text-start">
                            {/* ⚠️ The vertical padding is ASYMMETRIC on purpose, and
                                the amount differs per direction.
                                Symmetric padding centres the font's EM BOX, not the
                                ink, and Thmanyah Sans reserves a lot of ascent for
                                stacked diacritics that these labels never use — so
                                the glyphs land low in the pill. Measured with an
                                alpha-weighted centroid of the rendered label (the
                                optical centre, which the font-metric bounding box
                                understates by more than half): the Arabic sat
                                10.8px low at a 54px size, i.e. 0.200em, while the
                                English sat only 3.5px = 0.065em low because "Who we
                                are" has ascenders pulling its mass up. One shared
                                correction would over-shoot English by ~7px, hence
                                the `ltr:` override.
                                `--nudge` is subtracted from the top and added to the
                                bottom, so the pill's height is unchanged and only
                                the text moves. */}
                            <span className="bg-brand-teal font-heading inline-block rounded-full px-[clamp(1.1rem,3.6vw,3.4rem)] pt-[calc(var(--pad)-var(--nudge))] pb-[calc(var(--pad)+var(--nudge))] text-[clamp(1.05rem,3.75vw,3.4rem)] leading-tight font-black text-white [--nudge:0.2em] [--pad:clamp(0.3rem,1.2vw,1rem)] ltr:[--nudge:0.065em]">
                                {t('about.whoWeAre.badge')}
                            </span>
                        </div>

                        {/* The 0.95rem floor on the copy is deliberate: 2.1vw
                            alone bottoms out around 8px on a phone, so the
                            clamp's lower bound is what keeps this readable as
                            body copy on small screens. */}
                        <div className="mt-[clamp(1rem,3.6vw,3rem)] space-y-[clamp(0.5rem,1.4vw,1.2rem)] px-[clamp(1rem,3.5vw,3.2rem)] text-center">
                            {paragraphs.map((paragraph, i) => (
                                <p key={i} className="text-[clamp(0.95rem,2.1vw,2rem)] leading-[1.75] text-white">
                                    {paragraph}
                                </p>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
