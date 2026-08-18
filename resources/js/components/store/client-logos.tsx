import { useTranslation } from 'react-i18next';

/**
 * "جهات تثق برطاب" — the client logo wall, the last section of the homepage,
 * below Client Reviews.
 *
 * Two rows of white tiles drifting in opposite directions, desaturated at rest
 * and full colour on hover. Three decisions worth keeping:
 *
 * 🔑 LIGHT ground, decided by measurement not taste. Composited against the brand
 * teal, six of these logos nearly disappear — OSUS and Al Majed Oud are black
 * marks, and Sultan City, King Salman, Ratbli and Rakez all lose their wordmarks.
 * They are dark-on-transparent lockups made for white.
 *
 * 🔑 Greyscale at rest is doing real work, not decoration. Fourteen logos carry
 * fourteen unrelated brand colours (STC purple, Ratbli magenta, Rakez gold,
 * Takaful blue, SASO green, a six-colour Ministry of Culture) which is visual
 * chaos beside this site's restrained cream/teal/gold. Desaturating unifies the
 * wall into one texture, and it also stops King Saud University's built-in solid
 * blue plate from reading as the odd one out.
 *
 * 🔑 A marquee rather than a grid because of the phone. Fourteen tiles in a grid
 * is either seven tall rows or tiles too small to read; a marquee shows the whole
 * set at a fixed height and moves itself. Under prefers-reduced-motion it becomes
 * a wrapped static grid — see app.css.
 *
 * The images are pre-normalised at build time: each trimmed to its ink and scaled
 * so all fourteen share roughly the same opaque-pixel AREA on one common 400x176
 * canvas. That is why every tile here can use identical CSS with no per-logo
 * tweaks, despite ink aspect ratios spanning 0.80:1 to 4.37:1.
 */

/** Slug = the file in public/images/clients; the name is localized for `alt`. */
const LOGOS = [
    'stc',
    'ksu',
    'moc',
    'modon',
    'saudi-standards',
    'rajhi-takaful',
    'kfmc',
    'pnu',
    'ksrelief',
    'sbahc',
    'osus',
    'rakez',
    'al-majed-oud',
    'ratbli',
] as const;

/** Split into two rows so the counter-drift has something to counter. */
const ROWS = [LOGOS.slice(0, 7), LOGOS.slice(7)];

function Tile({ slug }: { slug: string }) {
    const { t } = useTranslation();

    return (
        <div className="px-2 sm:px-3">
            {/* ⚠️ `transition`, not `transition-[transform,…]`. Tailwind v4 emits
                `-translate-y-1` as the standalone `translate` property rather than
                composing it into `transform`, so a transform-scoped transition
                does not cover the lift and it snaps instead of easing. (Same trap
                makes getComputedStyle().transform read `none` while the lift is
                working — measure `translate`.) The default list includes
                translate, box-shadow and colours. */}
            <div className="group/logo hover:shadow-brand-teal/10 hover:ring-brand-gold/50 flex h-[72px] w-[152px] items-center justify-center rounded-2xl bg-white px-4 ring-1 ring-neutral-200/70 transition duration-300 hover:-translate-y-1 hover:shadow-lg sm:h-[88px] sm:w-[196px] sm:rounded-3xl sm:px-5">
                <img
                    src={`/images/clients/${slug}.webp`}
                    alt={t(`clientLogos.names.${slug}`)}
                    width={400}
                    height={176}
                    loading="lazy"
                    decoding="async"
                    /* ⚠️ The rest opacity is 85, not 70. Measured mean ink
                       luminance across the set spans 0 (OSUS, pure black) to 143
                       (Rakez gold); at 0.70 the lightest marks landed at an
                       effective 176 against white, which is too thin to read.
                       0.85 keeps the wall quiet without erasing its weakest
                       members. Note the fade is uniform — it cannot equalise the
                       spread, only soften the whole wall. */
                    className="max-h-full w-full object-contain opacity-85 grayscale transition-[filter,opacity] duration-500 group-hover/logo:opacity-100 group-hover/logo:grayscale-0"
                />
            </div>
        </div>
    );
}

function Row({ slugs, reverse, duration }: { slugs: readonly string[]; reverse: boolean; duration: string }) {
    return (
        /*
         * Masked at both ends so tiles dissolve into the section instead of being
         * cut off mid-logo, which is the detail that separates a marquee that
         * looks designed from one that looks like an overflow bug.
         *
         * 🔴 `py-4` is load-bearing, not spacing. The row must clip HORIZONTALLY
         * (the track is far wider than the viewport), but `overflow-hidden` clips
         * both axes — so a tile lifting 4px on hover had its top edge and its
         * shadow sliced off.
         *
         * ⚠️ `overflow-x-hidden overflow-y-visible` is NOT the fix: per spec, when
         * one axis is `hidden` the other computes `visible` to `auto`, so that
         * trades the clipping for a stray scrollbar. Padding the row instead keeps
         * the lift inside the overflow box, where nothing clips it.
         *
         * Smaller below `sm` because there is no hover on touch, so the phone only
         * needs the padding for row separation, not for a lift it never shows.
         */
        <div
            className="logo-marquee-row relative flex overflow-hidden py-3 [mask-image:linear-gradient(to_right,transparent,black_8%,black_92%,transparent)] sm:py-4"
            // dir is pinned so the animation's fixed-sign translate drifts the
            // same way in both locales (see app.css).
            dir="ltr"
        >
            {[false, true].map((isClone) => (
                <div
                    key={String(isClone)}
                    className="logo-marquee"
                    data-marquee-clone={isClone}
                    // The clone exists only to make the loop seamless, so it must
                    // not be announced — otherwise a screen reader hears all
                    // fourteen brands twice.
                    aria-hidden={isClone || undefined}
                    style={
                        {
                            '--marquee-duration': duration,
                            '--marquee-direction': reverse ? 'reverse' : 'normal',
                        } as React.CSSProperties
                    }
                >
                    {slugs.map((slug) => (
                        <Tile key={slug} slug={slug} />
                    ))}
                </div>
            ))}
        </div>
    );
}

export default function ClientLogos() {
    const { t } = useTranslation();

    /*
     * The section background is a vertical gradient that hands off into the
     * footer. It starts at #f9f7f2 — exactly what `bg-brand-cream/60` (#f5f1ea at
     * 60% over white) used to render as — and ends at #f6e8d4, the first stop of
     * the footer's own gradient, so the section's bottom edge and the footer's top
     * edge are the same colour and the seam disappears.
     *
     * ⚠️ Both ends are literal hex on purpose. The start cannot stay
     * `bg-brand-cream/60` because a gradient stop takes a colour, not a composited
     * result; and the end tracks the FOOTER, so if that gradient is ever retuned
     * this value has to move with it.
     */
    return (
        <section className="relative w-full overflow-hidden bg-gradient-to-b from-[#f9f7f2] to-[#f6e8d4] py-12 sm:py-16">
            <div className="relative z-10 mx-auto max-w-[1600px] px-6 lg:px-12">
                <div className="mb-8 text-center sm:mb-10">
                    <h2 className="font-heading text-brand-gold text-[clamp(1.75rem,3vw,2.5rem)] font-black">{t('clientLogos.title')}</h2>
                    <p className="text-brand-teal/70 mx-auto mt-2 max-w-2xl text-sm sm:text-base">{t('clientLogos.subtitle', { n: LOGOS.length })}</p>
                </div>
            </div>

            {/* Full-bleed, outside the padded container: the rows have to run to
                the viewport edges for the edge mask to make sense.

                No gap here — each row already carries its own vertical padding for
                the hover lift, and that padding is what separates the rows. Adding
                a gap on top would double-count it and push them apart. */}
            <div className="relative flex flex-col">
                {ROWS.map((slugs, i) => (
                    <Row key={i} slugs={slugs} reverse={i % 2 === 1} duration={i === 0 ? '46s' : '58s'} />
                ))}
            </div>
        </section>
    );
}
