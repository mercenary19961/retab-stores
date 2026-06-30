import { Head, Link } from '@inertiajs/react';
import StoreLayout from '@/layouts/store-layout';

interface Product {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    description_ar: string | null;
    description_en: string | null;
    price: number;
    sale_price: number | null;
    effective_price: number;
    on_sale: boolean;
    in_stock: boolean;
    category: { name_ar: string; name_en: string | null; slug: string } | null;
}

export default function ShopProduct({ product }: { product: Product }) {
    return (
        <StoreLayout>
            <Head title={product.name_ar} />

            <nav className="mb-6 text-sm text-gray-500">
                <Link href="/" className="hover:underline">الرئيسية</Link>
                {product.category && (
                    <>
                        {' / '}
                        <Link href={`/?category=${product.category.slug}`} className="hover:underline">
                            {product.category.name_ar}
                        </Link>
                    </>
                )}
            </nav>

            <div className="grid gap-8 md:grid-cols-2">
                <div className="flex aspect-square items-center justify-center rounded-lg bg-[#f1ede7] text-7xl">🌴</div>

                <div>
                    <h1 className="text-2xl font-bold">{product.name_ar}</h1>

                    <div className="mt-3 flex items-center gap-3">
                        {product.on_sale ? (
                            <>
                                <span className="text-2xl font-bold text-[#2f4f4f]">{product.effective_price} ر.س</span>
                                <span className="text-gray-400 line-through">{product.price} ر.س</span>
                            </>
                        ) : (
                            <span className="text-2xl font-bold text-[#2f4f4f]">{product.price} ر.س</span>
                        )}
                    </div>

                    {product.description_ar && (
                        <p className="mt-4 leading-relaxed text-gray-600">{product.description_ar}</p>
                    )}

                    <div className="mt-6">
                        {product.in_stock ? (
                            <button
                                type="button"
                                className="rounded-lg bg-[#2f4f4f] px-6 py-3 font-semibold text-white transition hover:bg-[#264141]"
                            >
                                أضف إلى السلة
                            </button>
                        ) : (
                            <span className="rounded-lg bg-gray-200 px-6 py-3 font-semibold text-gray-500">
                                غير متوفر حالياً
                            </span>
                        )}
                    </div>
                </div>
            </div>
        </StoreLayout>
    );
}
