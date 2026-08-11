import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface Item {
    key: string;
    title: string;
    lines: string[];
}

interface FooterSettings {
    contact_phone: string;
    contact_email: string;
}

/**
 * Contact Us → "معلومات الإتصال" (Figma: contact-us-contact-info).
 *
 * Four teal cards in a 2×2 grid over a faint brand-knot watermark. The hand-off
 * included the SVG, so the geometry here is exact rather than measured, and every
 * number was then confirmed against the PNG's pixels:
 *
 *   card 591 × 295, rx 73 (aspect 2.003:1)
 *   columns at x 119..710 and 747.5..1338.5   → gap 37.5
 *   rows    at y 326..621 and 674..969        → gap 53
 *
 * ⚠️ The design's side margins are 119 left / 101.5 right, i.e. the whole block sits
 * 8.75px RIGHT of centre. That is drift, not intent (0.6% of the frame), so the block
 * is centred here on the mean margin of 110.25 → container 1219.5/1440 = 84.69%, with
 * the gap kept as 37.5/1219.5 = 3.08% of the container so the designer's rhythm
 * survives at any width.
 *
 * Type was derived from ink and cross-checked two ways, per the recipe in CLAUDE.md.
 * Every one of seven strings matched the design's ink height to within 1px at:
 *
 *   heading  121px / weight 900 / brand-teal
 *   titles    53px / weight 700 / white
 *   bodies    53px / weight 400 / brand-gold      line pitch 68-69px → 1.3
 *
 * 🔑 Titles and bodies are the SAME size — weight and colour carry the hierarchy, not
 * scale (exactly as in the why-us section). The clean anchors were the two Latin
 * bodies and "و دول الخليج", the only three strings with no kashida, whose
 * width-derived and height-derived sizes agree to within 0.4px. Weights were pinned
 * separately by stroke thickness (design stems 18/6/3px against rendered w900=17,
 * w700=6, w400=4 at those sizes) because ink height barely discriminates weight.
 *
 * 🔴 The frame is 1126px tall, which cannot fit a laptop viewport once the 114px
 * navbar is counted. Whitespace is roughly halved and the content scaled to 0.82,
 * targeting ~800px. Every clamp() caps at its 1440px value so the section stops
 * growing on a larger monitor instead of scaling itself back out of view.
 */
export default function ContactInfo() {
    const { t } = useTranslation();
    const footer = (usePage().props as unknown as { footer: FooterSettings }).footer;

    const raw = t('contact.info.items', { returnObjects: true, defaultValue: [] }) as unknown;
    const items = (Array.isArray(raw) ? raw : []) as Item[];

    // 🔑 Phone and email come from the SAME admin-editable settings the footer reads,
    // never from i18n. The composite shows "info@retabstore.com" — the OLD Zid domain,
    // already stale — which is exactly what hardcoding design copy would have shipped.
    // Anything the client can change in the admin has to resolve at render time.
    const values: Record<string, string[]> = {
        phone: [footer.contact_phone],
        email: [footer.contact_email],
    };

    return (
        <section className="relative w-full overflow-hidden bg-white pt-[clamp(1rem,2.64vw,2.375rem)] pb-[clamp(1.5rem,4.17vw,3.75rem)]">
            {/* Brand knot, physically top-left in both locales: the crop is baked to
                bleed off that corner, so mirroring it under LTR would turn the cut
                edge inward and read as a clipping bug (same as journey / why-us).

                🔑 Placement is EXACT, not estimated. Compositing this asset over white
                at its natural size at (0,0) and diffing against the design PNG gives a
                mean deviation of 0.02/255 (max 11) across every background pixel,
                against 1.28 stretched and 3.45 mirrored — so the designer exported the
                already-rotated layer at frame scale. It also needs NO opacity of its
                own: the file is pre-faded to 7.9% (alpha 117/127) and the composite's
                #F9F7F2 is that colour over white on all three channels exactly.

                ⚠️ Sized by HEIGHT, not width, because the section is compressed to ~0.7
                of the frame and anchoring by width would leave the artwork's aspect
                fighting the shorter box. 125% rather than 100% is measured, not chosen:
                comparing the render to the composite with the same pixel analysis, the
                watermark's TINT already matched at any size (12.90 vs 13.6 of 255,
                since the asset carries its own opacity) but its COVERAGE of the visible
                background did not — 16.5% at h-full against the design's 20.9%, because
                a shorter section scales the image down. Sweeping the height gives 16.5 /
                18.8 / 20.0 / 21.7 / 25.0% at 100/110/120/130/140%, and 125% measures
                19.5% in place: not the arithmetic 20.9, and deliberately left slightly
                under rather than pushed to 130% (overshooting a texture reads worse than
                undershooting it). The overflow-hidden above crops the lower quarter of
                the knot, which is invisible in an abstract shape at 8% opacity.

                ⚠️ No negative z-index — `position: relative` does not create a stacking
                context, so a negatively stacked child paints behind the nearest
                ANCESTOR background and the watermark would vanish. Plain absolute
                first in DOM order plus a relative content wrapper is enough. */}
            <img
                src="/images/contact/watermark.webp"
                alt=""
                aria-hidden
                className="pointer-events-none absolute top-0 left-0 h-[125%] w-auto select-none"
            />

            <div className="relative mx-auto w-[84.69%] max-w-[1219px]">
                {/* ⚠️ text-start, NOT text-end. These are logical properties resolved
                    against reading direction, so `end` means LEFT in Arabic and RIGHT
                    in English — inverted in both locales at once, which is the bug that
                    shipped on the journey heading. The design puts this heading on the
                    right in Arabic, which is `start`.

                    The design's heading ink sits 43px further right than the card
                    block's right edge, on a right margin that matches nothing else in
                    the frame. Aligning it to the card container instead is the only
                    relationship here that is reproducible and reads as deliberate. */}
                <h2 className="text-brand-teal font-heading text-start text-[clamp(1.75rem,6.875vw,6.1875rem)] leading-[1.15] font-black">
                    {t('contact.info.heading')}
                </h2>

                {/* auto-rows-fr makes every row the same height, so all four cards match
                    as they do in the design (591 × 295 each) even though the lower two
                    carry two body lines and the upper two carry one. Without it each row
                    sizes to its own content and the grid steps.

                    Deliberately NOT gated to `sm:` — in a single column the phone and
                    email cards would then be visibly squatter than the two below them,
                    which reads as cramped rather than as intentional.

                    One column below `sm`: two 170px-wide cards on a phone cannot hold an
                    email address at any readable size. */}
                <ul className="mt-[clamp(1rem,3.47vw,3.125rem)] grid auto-rows-fr grid-cols-1 gap-x-[3.08%] gap-y-[clamp(0.75rem,1.875vw,1.6875rem)] sm:grid-cols-2">
                    {items.map((item) => (
                        <li
                            key={item.key}
                            /* rx 73 on a 295-tall card is 0.247 of its height, so the
                               radius is scaled with everything else rather than left
                               at 73 (which would read as a pill once the card is
                               shorter). Content is centred: the design's upper cards
                               sit ~35px high with slack beneath them, which is
                               hand-placement rather than a rule — their title-to-body
                               gap is 68px against the lower cards' 26px, and no
                               consistent anchor explains both. Centring is stable when
                               the copy changes. */
                            className="bg-brand-teal flex flex-col items-center justify-center rounded-[clamp(1rem,4.17vw,3.75rem)] px-[6%] py-[clamp(1rem,2.36vw,2.125rem)] text-center"
                        >
                            <h3 className="font-heading text-[clamp(1.05rem,3.02vw,2.71875rem)] leading-[1.2] font-bold text-white">{item.title}</h3>

                            {/* Same size as the title, one weight lighter and gold.
                                ⚠️ English gets its own slightly smaller size. Latin
                                words run much longer than these Arabic labels at the
                                same size, and a single wrapped line grows every card
                                (auto-rows-fr) and took the section from 797px to 910px.
                                At 40.5px even the longest line considered fits the
                                520px of inner width, so the copy has real headroom
                                rather than surviving on a 25px margin. */}
                            <p className="text-brand-gold mt-[clamp(0.35rem,1.46vw,1.3125rem)] text-[clamp(1.05rem,3.02vw,2.71875rem)] leading-[1.3] font-normal ltr:text-[clamp(1rem,2.8125vw,2.53125rem)]">
                                {/* 🔴 `dir="ltr"` belongs on the SPAN, never on the <p>
                                    that carries the `ltr:` size. Tailwind's ltr variant
                                    resolves against the element's OWN direction, so a
                                    dir on the paragraph made the phone and email cards
                                    take the English size while still in Arabic — two of
                                    four cards silently a size smaller. The isolation is
                                    still wanted (project convention for numbers and
                                    addresses: a leading + or an @-address must not be
                                    reordered mid-string by the RTL algorithm), it just
                                    has to sit on the value itself. */}
                                {(values[item.key] ?? item.lines).map((line, i) => (
                                    <span key={i} dir={values[item.key] ? 'ltr' : undefined} className="block">
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
