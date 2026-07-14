import { useTranslation } from 'react-i18next';

/**
 * Full-bleed promotional banner ("تمورنا سعودية المنشأ"). The artwork is a
 * self-contained SVG (embedded product photo + geometric pattern + vector text
 * + CTA pill). Rendered as a background sized to cover a true 100vw box, so it
 * spans the entire screen width regardless of any parent constraint (the
 * `left-1/2 w-screen -translate-x-1/2` breakout). Only the baked-in "تسوّق الآن"
 * pill is clickable, via a transparent link over it (SVG rect x=949 y=338 w=272
 * h=82 within the 1448×508 viewBox).
 */
export default function PrimaryBanner() {
    const { t } = useTranslation();

    return (
        <section
            role="img"
            aria-label={t('primaryBanner.alt')}
            className="relative left-1/2 aspect-[1448/508] w-screen -translate-x-1/2 bg-cover bg-center bg-no-repeat"
            style={{ backgroundImage: "url('/images/banner/banner.svg')" }}
        >
            <a
                href="#products"
                aria-label={t('hero.cta')}
                className="absolute"
                style={{ left: '65.4%', top: '66.3%', width: '19%', height: '16.6%' }}
            />
        </section>
    );
}
