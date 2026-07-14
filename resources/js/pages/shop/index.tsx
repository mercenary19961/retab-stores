import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useLocalized } from '@/lib/localize';
import StoreLayout from '@/layouts/store-layout';
import StoreHero from '@/components/store/hero';
import BestSellers from '@/components/store/best-sellers';

interface Category {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
}

interface ProductCard {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    price: number;
    sale_price: number | null;
    effective_price: number;
    on_sale: boolean;
    is_featured: boolean;
    image: string | null;
    category: { name_ar: string; name_en: string | null; slug: string } | null;
}

export default function ShopIndex({
    products,
    bestSellers = [],
    activeCategory,
}: {
    categories: Category[];
    products: ProductCard[];
    bestSellers?: ProductCard[];
    activeCategory: string | null;
}) {
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');

    return (
        <StoreLayout bare>
            <Head title={t('shop.headTitle')}>
                <meta name="description" content={t('shop.metaDescription')} />
                <meta property="og:title" content={t('shop.headTitle')} />
                <meta property="og:type" content="website" />
            </Head>

            {/* Hero + best sellers show on the plain homepage, not a filtered view. */}
            {!activeCategory && (
                <>
                    <StoreHero />
                    <BestSellers products={bestSellers} />
                </>
            )}

            <div className="mx-auto max-w-6xl px-4 py-8">
                <h1 className="mb-6 text-2xl font-bold">{t('shop.heading')}</h1>

                {products.length === 0 ? (
                    <p className="text-gray-500">{t('shop.empty')}</p>
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {products.map((p) => (
                        <Link
                            key={p.id}
                            href={`/products/${p.slug}`}
                            className="group rounded-lg border border-gray-200 bg-white p-3 transition hover:shadow-md"
                        >
                            {p.image ? (
                                <img src={p.image} alt={localized(p, 'name')} className="mb-3 aspect-square w-full rounded-md object-cover" />
                            ) : (
                                <div className="mb-3 flex aspect-square items-center justify-center rounded-md bg-[#f1ede7] text-4xl">
                                    🌴
                                </div>
                            )}
                            <h3 className="line-clamp-2 text-sm font-semibold">{localized(p, 'name')}</h3>
                            <div className="mt-2 flex items-center gap-2">
                                {p.on_sale ? (
                                    <>
                                        <span className="font-bold text-[#2f4f4f]">{p.effective_price} {currency}</span>
                                        <span className="text-xs text-gray-400 line-through">{p.price} {currency}</span>
                                    </>
                                ) : (
                                    <span className="font-bold text-[#2f4f4f]">{p.price} {currency}</span>
                                )}
                            </div>
                        </Link>
                        ))}
                    </div>
                )}
            </div>
        </StoreLayout>
    );
}
