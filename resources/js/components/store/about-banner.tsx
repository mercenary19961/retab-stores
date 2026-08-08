import { useTranslation } from 'react-i18next';

/**
 * About Us hero banner (Figma: "about us - banner").
 *
 * Built the same way as the homepage hero (see store/hero.tsx): an aspect-locked
 * full-bleed photo with an absolutely-positioned text column. The column is
 * anchored PHYSICALLY left in both locales rather than to the reading direction,
 * because the dates box is baked into the right of the photo — mirroring for LTR
 * would drop the headline straight on top of it.
 *
 * The photo carries no text; the two lines and the teal band are live HTML, so
 * they stay translatable and selectable. (The designer's 3.2 MB export of the
 * whole composition as one SVG was reference only, never shipped.)
 */
export default function AboutBanner() {
    const { t } = useTranslation();

    return (
        <section className="relative w-full overflow-hidden">
            <img src="/images/about/banner.webp" alt="" width={1440} height={800} className="block h-auto w-full" fetchPriority="high" />

            {/* The left of the photo is bright sky and sand, so the teal first line
                needs the same lift the homepage hero uses. */}
            <div className="pointer-events-none absolute inset-0 bg-gradient-to-r from-white/45 via-white/10 to-transparent" />

            {/* Width is the Figma's teal rectangle: 727 of a 1440 banner ≈ 50.5%.
                That number is doing real work — the dates box starts at about 52%
                of the photo, so a wider column drops the headline on top of it. */}
            <div className="absolute inset-y-0 left-0 flex w-[50.5%] min-w-[240px] items-center pl-[5%] max-sm:min-w-0">
                {/* The cap is 5.5rem = 4.6vw at 1920px, i.e. the type holds its
                    Figma proportion of the photo all the way to a 1920 viewport
                    before it stops growing. Capping lower makes the lockup shrink
                    against the photo on wide screens. */}
                <h1 className="font-heading w-full text-start text-[clamp(1.35rem,4.6vw,5.5rem)] leading-[1.18] font-black max-[732px]:text-[clamp(0.95rem,3.6vw,1.5rem)]">
                    {/* Same start inset as the band's text below, so both lines
                        align on the edge they are aligned to. Without it line 1 runs
                        to the column edge while line 2 sits inset inside the band. */}
                    <span className="text-brand-teal block ps-[1.4vw]">{t('about.banner.line1')}</span>

                    {/* Teal band. In the Figma its rectangle overshoots its own frame
                        to sit flush against the banner's left edge, so it is bled
                        rather than boxed: -ml/pl of 100vw stretches the background
                        leftwards without moving the text, and the section's
                        overflow-hidden clips the excess.

                        Padding is in vw, not rem: the photo is aspect-locked, so its
                        rendered height scales with viewport WIDTH. A fixed padding
                        would be the right share of the banner at 1440px and far too
                        thin at 1920px, drifting from the Figma's 131/800 band. */}
                    <span className="bg-brand-teal mt-[1.2vw] -ml-[100vw] block py-[clamp(0.35rem,1.85vw,2.2rem)] pl-[100vw] text-white">
                        {/* `ps-`, not `pe-`: text-start puts the text on the START
                            side, so the start side is the one that needs the inset
                            (right in Arabic, left in English). */}
                        <span className="block ps-[1.4vw]">{t('about.banner.line2')}</span>
                    </span>
                </h1>
            </div>
        </section>
    );
}
