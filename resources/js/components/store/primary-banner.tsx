import { useTranslation } from 'react-i18next';

/**
 * Full-bleed promotional banner ("تمورنا سعودية المنشأ"). Same width mechanism
 * as the hero: a plain `w-full` <img> inside a `w-full` section, so it reaches
 * exactly the same right edge as every other full-width section (no scrollbar
 * breakout math). The artwork is a self-contained SVG (embedded product photo +
 * geometric pattern + vector text + CTA pill). Only the baked-in "تسوّق الآن"
 * pill is clickable, via a transparent link over it (SVG button rect x=949 y=338
 * w=272 h=82 within the 1448×508 viewBox).
 */
export default function PrimaryBanner() {
    const { t } = useTranslation();

    return (
        <section className="relative w-full">
            <img
                src="/images/banner/banner.svg"
                alt={t('primaryBanner.alt')}
                className="block h-auto w-full"
            />
            <a
                href="#products"
                aria-label={t('hero.cta')}
                className="absolute"
                style={{ left: '65.6%', top: '67.6%', width: '18.9%', height: '16.4%' }}
            />
        </section>
    );
}
