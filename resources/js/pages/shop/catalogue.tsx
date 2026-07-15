import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useLocalized } from '@/lib/localize';
import StoreLayout from '@/layouts/store-layout';

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

export default function ShopCatalogue({
    categories,
    products,
    activeCategory,
}: {
    categories: Category[];
    products: ProductCard[];
    activeCategory: string | null;
}) {
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');

    const chip = (active: boolean) =>
        `rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
            active ? 'bg-brand-teal text-white' : 'bg-brand-cream text-brand-teal hover:bg-brand-gold/20'
        }`;

    return (
        <StoreLayout>
            <Head title={t('catalogue.headTitle')}>
                <meta name="description" content={t('shop.metaDescription')} />
            </Head>

            <h1 className="mb-6 text-center font-heading font-black text-brand-teal text-[clamp(1.75rem,4vw,2.75rem)]">
                {t('catalogue.heading')}
            </h1>

            <div className="mb-8 flex flex-wrap justify-center gap-2">
                <Link href="/shop" className={chip(!activeCategory)}>
                    {t('catalogue.all')}
                </Link>
                {categories.map((c) => (
                    <Link key={c.id} href={`/shop?category=${c.slug}`} className={chip(activeCategory === c.slug)}>
                        {localized(c, 'name')}
                    </Link>
                ))}
            </div>

            {products.length === 0 ? (
                <p className="text-center text-gray-500">{t('shop.empty')}</p>
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
                                <div className="mb-3 flex aspect-square items-center justify-center rounded-md bg-brand-cream text-4xl">🌴</div>
                            )}
                            <h3 className="line-clamp-2 text-sm font-semibold">{localized(p, 'name')}</h3>
                            <div className="mt-2 flex items-center gap-2">
                                {p.on_sale ? (
                                    <>
                                        <span className="font-bold text-brand-teal">{p.effective_price.toFixed(2)} {currency}</span>
                                        <span className="text-xs text-gray-400 line-through">{p.price.toFixed(2)} {currency}</span>
                                    </>
                                ) : (
                                    <span className="font-bold text-brand-teal">{p.price.toFixed(2)} {currency}</span>
                                )}
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </StoreLayout>
    );
}
