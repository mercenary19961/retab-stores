import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

/**
 * Full-bleed promotional banner. Both crops are just the product photo with an
 * empty teal area for the copy; the title, subtitle and CTA are real overlaid
 * elements (so they're translatable and the button is a genuine link).
 *
 * The two crops put that empty area in DIFFERENT places, so the overlay moves
 * with them rather than just being rescaled:
 *   desktop (1440x500) — products left, teal on the RIGHT  → overlay inset-y-0 right-0
 *   phone   (402x874)  — products bottom, teal at the TOP  → overlay top-0 h-[58%]
 * Measured on the portrait crop: rows are flat teal (sd 2-12) down to ~59% of the
 * height, then sd jumps to ~66 where the product cluster starts, so the copy has
 * to stay above that. Desktop keeps its overlay on the PHYSICAL right because the
 * photo doesn't mirror; the phone band is full-width so direction doesn't apply.
 */
export default function PrimaryBanner() {
    const { t } = useTranslation();

    return (
        <section className="relative w-full overflow-hidden">
            {/* One source is fetched, not both — a phone never downloads the
                86 KB landscape crop it won't display. */}
            <picture>
                <source media="(max-width: 639px)" srcSet="/images/banner/banner-mobile.webp" />
                <img src="/images/banner/banner.webp" alt={t('primaryBanner.alt')} className="block h-auto w-full" />
            </picture>

            <div className="absolute inset-y-0 right-0 flex w-[48%] flex-col items-center justify-center gap-[1.5%] px-[2%] text-center max-sm:inset-y-auto max-sm:top-0 max-sm:h-[58%] max-sm:w-full max-sm:gap-3 max-sm:px-6">
                {/* On phones the type is sized off the viewport rather than the old
                    vw ramp: that ramp was calibrated to a banner only ~135px tall at
                    390px wide, so it produced tiny text on the full-height crop. */}
                <h2 className="font-heading text-brand-gold text-[clamp(1.75rem,9vw,7.5rem)] leading-none font-black max-sm:text-[clamp(2.25rem,11vw,3.5rem)]">
                    {t('primaryBanner.title')}
                </h2>
                <p className="font-heading text-[clamp(0.85rem,3.6vw,3rem)] leading-tight font-bold text-white max-sm:text-[clamp(1rem,4.5vw,1.5rem)]">
                    {t('primaryBanner.subtitle')}
                </p>
                {/* The CTA inverts on phones, and it is a contrast fix rather than a
                    style preference. Measured against each crop at the button's own
                    measured position: on desktop it lands at y≈71%, on the light beige
                    floor, where the teal pill scores 5.40:1. The portrait crop puts it
                    at y≈35% on the dark teal wall (#163639), where the same pill scores
                    1.39:1 — under the 3:1 floor for UI components, and only visible at
                    all thanks to its ring. White there scores 12.91:1.
                    `[--shimmer-base]` is required, not decorative: see the note on
                    .cta-shimmer — without it the label stays white and disappears. */}
                <Link
                    href="/shop"
                    className="bg-brand-teal font-heading hover:bg-brand-teal/90 mt-[3%] inline-block rounded-full px-[7%] py-[1.6%] text-[clamp(0.8rem,2.4vw,2rem)] font-black text-white shadow-xl ring-1 ring-white/20 transition-colors max-sm:mt-2 max-sm:bg-white max-sm:px-7 max-sm:py-3 max-sm:text-[1rem] max-sm:ring-black/5 max-sm:[--shimmer-base:#1b4e53] max-sm:hover:bg-white"
                >
                    <span className="cta-shimmer">{t('hero.cta')}</span>
                </Link>
            </div>
        </section>
    );
}
