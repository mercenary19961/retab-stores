import { useTranslation } from 'react-i18next';

/**
 * About Us → "من نحن" (Figma: "about us - who we are").
 *
 * Unlike the banner above it, this section is NOT aspect-locked to its artwork.
 * The photo is a sand texture used as a backdrop and the card on top is text,
 * which has to be free to grow: locking the section to the artwork's 1.8:1 would
 * leave a 217px-tall band on a 390px phone with nowhere for the copy to go. So
 * the background is an object-cover layer and the section's height follows its
 * content. Cropping the texture is harmless; clipping the text would not be.
 *
 * The card is Figma's Rectangle 24 (#FFF8F8 at 11%, 66px radius) plus the
 * background blur Figma applies as an effect — effects are not carried in an SVG
 * export, so it is reproduced here with backdrop-blur.
 */
export default function AboutWhoWeAre() {
    const { t } = useTranslation();

    // i18next returns the raw array for a key whose value is an array; the guard
    // keeps a mistyped or missing key from throwing at render.
    const raw = t('about.whoWeAre.paragraphs', { returnObjects: true, defaultValue: [] }) as unknown;
    const paragraphs = Array.isArray(raw) ? (raw as string[]) : [];

    return (
        <section className="relative w-full py-[clamp(1.75rem,5.2vw,5rem)]">
            {/* 🔑 The seam with the banner above is CROSS-FADED, not butted.
                These are two different photographs — the banner is a wide scene shot
                at distance, this is a macro close-up of ground — so their edges do
                not share tone or grain. Measured at the join, the average colour
                differs by 22/29/10/23 across four horizontal bands: uneven, which is
                what gives it away as different source images rather than a scale
                mismatch (both render 1:1 at 1440).

                So the image is bled UPWARDS over the banner by the fade distance and
                masked from transparent to opaque, which blends real pixels from both
                photos instead of butting two mismatched edges together. `object-top`
                still anchors the crop to the top so the bottom is what gets trimmed.

                ⚠️ The section must NOT be `overflow-hidden` or the upward bleed is
                clipped away and the hard line comes back.

                ⚠️ The height is stated explicitly as `calc(100% + bleed)`. Setting
                `top` and `bottom` alone does NOT stretch an <img>: a replaced
                element with height:auto resolves its height from the intrinsic
                aspect ratio, so it rendered 217px tall inside a 357px section and
                left the bottom of the section with no background. Desktop masked
                the bug because the auto height happened to exceed the section
                there — it only showed on mobile. */}
            <img
                src="/images/about/who-we-are.webp"
                alt=""
                className="absolute inset-x-0 -top-[10vw] h-[calc(100%+10vw)] w-full object-cover object-top [-webkit-mask-image:linear-gradient(to_bottom,transparent_0,#000_10vw)] [mask-image:linear-gradient(to_bottom,transparent_0,#000_10vw)]"
                loading="lazy"
            />

            {/* 1190 of a 1440 frame ≈ 82.6%, capped at the artwork's own width so
                the card never outgrows the design on very wide screens. */}
            <div className="relative mx-auto w-[82.6%] max-w-[1190px] rounded-[clamp(1.25rem,4.6vw,66px)] bg-[#FFF8F8]/[0.11] pt-[clamp(0.6rem,1.4vw,1.3rem)] pb-[clamp(1.5rem,4vw,3.5rem)] ring-1 ring-white/25 backdrop-blur-md">
                {/* The badge is tucked into the card's top corner in the design, so
                    it sits nearly flush while the copy below is properly inset.
                    Positioned logically (`ps-`), not physically: nothing in the
                    photo anchors it, so it follows the reading direction and moves
                    to the left in English. */}
                <div className="ps-[0.6vw] text-start">
                    <span className="bg-brand-teal font-heading inline-block rounded-full px-[clamp(1.1rem,3.6vw,3.4rem)] py-[clamp(0.3rem,1.2vw,1rem)] text-[clamp(1.05rem,3.75vw,3.4rem)] leading-tight font-black text-white">
                        {t('about.whoWeAre.badge')}
                    </span>
                </div>

                {/* The 0.95rem floor on the copy is deliberate: 2.1vw alone bottoms
                    out around 8px on a phone, so the clamp's lower bound is what
                    keeps this readable as body copy on small screens. */}
                <div className="mt-[clamp(1rem,3.6vw,3rem)] space-y-[clamp(0.5rem,1.4vw,1.2rem)] px-[clamp(1rem,3.5vw,3.2rem)] text-center">
                    {paragraphs.map((paragraph, i) => (
                        <p key={i} className="text-[clamp(0.95rem,2.1vw,2rem)] leading-[1.75] text-white">
                            {paragraph}
                        </p>
                    ))}
                </div>
            </div>
        </section>
    );
}
