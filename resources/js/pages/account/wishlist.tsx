import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useLocalized } from '@/lib/localize';
import StoreLayout from '@/layouts/store-layout';

interface Item {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    price: number;
    sale_price: number | null;
    effective_price: number;
    in_stock: boolean;
}

export default function Wishlist({ items }: { items: Item[] }) {
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');

    return (
        <StoreLayout>
            <Head title={t('wishlist.title')} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-bold">{t('wishlist.title')}</h1>
                <Link href="/account" className="text-sm text-gray-500 underline">{t('wishlist.back')}</Link>
            </div>

            {items.length === 0 ? (
                <p className="text-sm text-gray-400">{t('wishlist.empty')}</p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {items.map((p) => (
                        <div key={p.id} className="rounded-lg border border-gray-200 bg-white p-4">
                            <Link href={`/products/${p.slug}`} className="font-semibold hover:underline">{localized(p, 'name')}</Link>
                            <div className="mt-2 text-[#2f4f4f]">
                                {p.sale_price !== null ? (
                                    <>
                                        <span className="font-bold">{p.effective_price} {currency}</span>{' '}
                                        <span className="text-sm text-gray-400 line-through">{p.price} {currency}</span>
                                    </>
                                ) : (
                                    <span className="font-bold">{p.price} {currency}</span>
                                )}
                            </div>
                            <div className="mt-3 flex items-center justify-between">
                                {p.in_stock ? (
                                    <button
                                        type="button"
                                        onClick={() => router.post('/cart', { product_id: p.id, quantity: 1 }, { preserveScroll: true })}
                                        className="rounded bg-[#2f4f4f] px-3 py-1.5 text-sm font-semibold text-white hover:bg-[#264141]"
                                    >
                                        {t('common.addToCart')}
                                    </button>
                                ) : (
                                    <span className="text-sm text-gray-400">{t('common.outOfStock')}</span>
                                )}
                                <button
                                    type="button"
                                    onClick={() => router.post(`/wishlist/${p.slug}/toggle`, {}, { preserveScroll: true })}
                                    className="text-sm text-red-500 hover:underline"
                                >
                                    {t('common.remove')}
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </StoreLayout>
    );
}
