import { useTranslation } from 'react-i18next';

/**
 * Footer trust-badge banner. The WebP carries the four feature icons, the
 * ornaments, the date photo and the palm watermark; the headline, the four icon
 * labels and the brand wordmark are overlaid as real (translatable) text.
 *
 * Positions are percentages of the 1440×471 artwork, so the text tracks the
 * baked-in icons as the image scales. The artwork does NOT mirror between
 * locales, so every position is physical (left %), identical in Arabic and
 * English. To nudge alignment, edit the numbers in BADGES / the style props.
 */
const BADGES = [
    { key: 'saudiMade', left: 9 }, // Saudi emblem
    { key: 'noPreservatives', left: 25 }, // no-flask
    { key: 'nonGmo', left: 40.8 }, // DNA + leaf
    { key: 'coolDry', left: 55.6 }, // thermometer + snowflake
] as const;

const LABELS_TOP = '66%'; // vertical line the four labels sit on

export default function FooterBanner() {
    const { t } = useTranslation();

    return (
        <section className="relative w-full overflow-hidden" aria-label={t('footerBanner.headline')}>
            <img src="/images/footer-banner/banner.webp" alt="" className="block h-auto w-full" />

            {/* Headline — sits in the cream space above the baked-in divider line */}
            <h2
                className="absolute -translate-x-1/2 -translate-y-1/2 whitespace-nowrap font-heading font-black leading-none text-brand-gold text-[clamp(0.85rem,3.4vw,2.6rem)]"
                style={{ left: '24%', top: '23%' }}
            >
                {t('footerBanner.headline')}
            </h2>

            {/* Brand wordmark — between the two floral ornaments on the right */}
            <span
                className="absolute -translate-x-1/2 -translate-y-1/2 whitespace-nowrap font-heading font-black leading-none text-brand-gold text-[clamp(0.8rem,3vw,2.3rem)]"
                style={{ left: '73%', top: '50%' }}
            >
                {t('footerBanner.brand')}
            </span>

            {/* Four feature labels, centred under their icons */}
            {BADGES.map((b) => (
                <span
                    key={b.key}
                    className="absolute -translate-x-1/2 -translate-y-1/2 whitespace-nowrap text-center font-heading font-medium leading-none text-brand-gold text-[clamp(0.4rem,1.35vw,1rem)]"
                    style={{ left: `${b.left}%`, top: LABELS_TOP }}
                >
                    {t(`footerBanner.badges.${b.key}`)}
                </span>
            ))}
        </section>
    );
}
