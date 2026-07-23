import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useLocalized } from '@/lib/localize';
import ProductImage from '@/components/store/product-image';

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

    const salePercent =
        p.on_sale && p.price > 0 ? Math.round((1 - p.effective_price / p.price) * 100) : 0;

    return (
        <Link href={`/products/${p.slug}`} className="group block">
            <div className="relative">
                <ProductImage src={p.image} alt={localized(p, 'name')} />

                {p.coming_soon ? (
                    <span className="absolute start-3 top-3 z-10 rounded-full bg-brand-teal px-2.5 py-1 font-heading text-xs font-bold text-white shadow-sm">
                        {t('catalogue.comingSoon')}
                    </span>
                ) : (
                    salePercent > 0 && (
                        <span className="absolute end-3 top-3 z-10 rounded-full bg-brand-gold px-2.5 py-1 font-heading text-xs font-bold text-white shadow-sm">
                            {t('catalogue.saleBadge', { percent: salePercent })}
                        </span>
                    )
                )}
            </div>

            <h3 className="mt-4 line-clamp-1 text-center font-heading text-brand-gold text-[clamp(1rem,2vw,1.35rem)]">
                {localized(p, 'name')}
            </h3>
            <div className="mt-1 text-center font-heading text-brand-teal">
                {p.coming_soon ? (
                    <span className="text-sm font-semibold text-brand-teal/70">{t('catalogue.requestCta')}</span>
                ) : p.on_sale ? (
                    <span className="inline-flex items-center gap-2">
                        <span className="font-bold">
                            {p.effective_price.toFixed(2)} {currency}
                        </span>
                        <span className="text-sm text-brand-teal/50 line-through">
                            {p.price.toFixed(2)} {currency}
                        </span>
                    </span>
                ) : (
                    <span className="font-bold">
                        {p.price.toFixed(2)} {currency}
                    </span>
                )}
            </div>
        </Link>
    );
}
