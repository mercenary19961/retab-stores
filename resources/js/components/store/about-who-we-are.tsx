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
        <section className="relative w-full overflow-hidden py-[clamp(1.75rem,5.2vw,5rem)]">
            <img src="/images/about/who-we-are.webp" alt="" className="absolute inset-0 h-full w-full object-cover" loading="lazy" />

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
