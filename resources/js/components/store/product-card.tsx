import ProductImage from '@/components/store/product-image';
import { useLocalized } from '@/lib/localize';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export interface StoreProduct {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    price: number;
    sale_price: number | null;
    effective_price: number;
    on_sale: boolean;
    is_featured: boolean;
    coming_soon: boolean;
    image: string | null;
    category: { name_ar: string; name_en: string | null; slug: string } | null;
}

/**
 * Brand product card for grids (catalogue, search results). Matches the visual
 * identity of the homepage carousel cards — rounded-[23%] square image, gold
 * heading name, teal price — and adds a gold sale badge with the discount %.
 */
export default function ProductCard({ product: p }: { product: StoreProduct }) {
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');

    const salePercent = p.on_sale && p.price > 0 ? Math.round((1 - p.effective_price / p.price) * 100) : 0;

    return (
        <Link href={`/products/${p.slug}`} className="group block">
            <div className="relative">
                <ProductImage src={p.image} alt={localized(p, 'name')} />

                {p.coming_soon ? (
                    <span className="bg-brand-teal font-heading absolute start-3 top-3 z-10 rounded-full px-2.5 py-1 text-xs font-bold text-white shadow-sm">
                        {t('catalogue.comingSoon')}
                    </span>
                ) : (
                    salePercent > 0 && (
                        <span className="bg-brand-gold font-heading absolute end-3 top-3 z-10 rounded-full px-2.5 py-1 text-xs font-bold text-white shadow-sm">
                            {t('catalogue.saleBadge', { percent: salePercent })}
                        </span>
                    )
                )}
            </div>

            {/* Two lines, ALWAYS two lines' worth of box — see the same note in
                product-carousel.tsx. Was `line-clamp-1`, which kept the grid even but
                cut most Arabic product names within a few words. */}
            <h3 className="font-heading text-brand-gold mt-4 line-clamp-2 min-h-[2lh] text-center text-[clamp(1rem,2vw,1.35rem)]">
                {localized(p, 'name')}
            </h3>
            <div className="font-heading text-brand-teal mt-1 text-center">
                {p.coming_soon ? (
                    <span className="text-brand-teal/70 text-sm font-semibold">{t('catalogue.requestCta')}</span>
                ) : p.on_sale ? (
                    // Stacked below `sm`, side by side above. Two prices do not fit one
                    // line on a phone-width card, and the `nowrap` is what stops an amount
                    // splitting from its currency ("100.00" over "SAR").
                    <span className="inline-flex flex-col items-center gap-0 sm:flex-row sm:gap-2">
                        <span className="font-bold whitespace-nowrap">
                            {p.effective_price.toFixed(2)} {currency}
                        </span>
                        <span className="text-brand-teal/50 text-sm whitespace-nowrap line-through">
                            {p.price.toFixed(2)} {currency}
                        </span>
                    </span>
                ) : (
                    <span className="font-bold whitespace-nowrap">
                        {p.price.toFixed(2)} {currency}
                    </span>
                )}
            </div>
        </Link>
    );
}
