import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

/**
 * Full-bleed promotional banner. The WebP is just the product photo with an
 * empty teal area on the right; the title, subtitle and CTA are real overlaid
 * elements (so they're translatable and the button is a genuine link). The
 * overlay sits on the physical right — the photo doesn't mirror, so it stays
 * over the empty area in both Arabic and English.
 */
export default function PrimaryBanner() {
    const { t } = useTranslation();

    return (
        <section className="relative w-full overflow-hidden">
            <img
                src="/images/banner/banner.webp"
                alt={t('primaryBanner.alt')}
                className="block h-auto w-full"
            />

            <div className="absolute inset-y-0 right-0 flex w-[48%] flex-col items-center justify-center gap-[1.5%] px-[2%] text-center">
                <h2 className="font-heading font-black leading-none text-brand-gold text-[clamp(1.75rem,9vw,7.5rem)]">
                    {t('primaryBanner.title')}
                </h2>
                <p className="font-heading font-bold leading-tight text-white text-[clamp(0.85rem,3.6vw,3rem)]">
                    {t('primaryBanner.subtitle')}
                </p>
                <Link
                    href="/shop"
                    className="mt-[3%] inline-block rounded-full bg-brand-teal px-[7%] py-[1.6%] font-heading font-black text-white shadow-xl ring-1 ring-white/20 transition-colors hover:bg-brand-teal/90 text-[clamp(0.8rem,2.4vw,2rem)]"
                >
                    <span className="cta-shimmer">{t('hero.cta')}</span>
                </Link>
            </div>
        </section>
    );
}
