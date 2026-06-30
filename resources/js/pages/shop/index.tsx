import { Head, Link } from '@inertiajs/react';
import StoreLayout from '@/layouts/store-layout';

interface Category {
    id: number;
    name_ar: string;
    slug: string;
}

interface ProductCard {
    id: number;
    name_ar: string;
    slug: string;
    price: number;
    sale_price: number | null;
    effective_price: number;
    on_sale: boolean;
    is_featured: boolean;
    image: string | null;
    category: { name_ar: string; slug: string } | null;
}

export default function ShopIndex({
    categories,
    products,
}: {
    categories: Category[];
    products: ProductCard[];
    activeCategory: string | null;
}) {
    return (
        <StoreLayout categories={categories}>
            <Head title="رطاب للتمور — تمور فاخرة" />

            <h1 className="mb-6 text-2xl font-bold">منتجاتنا</h1>

            {products.length === 0 ? (
                <p className="text-gray-500">لا توجد منتجات في هذا التصنيف.</p>
            ) : (
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    {products.map((p) => (
                        <Link
                            key={p.id}
                            href={`/products/${p.slug}`}
                            className="group rounded-lg border border-gray-200 bg-white p-3 transition hover:shadow-md"
                        >
                            {p.image ? (
                                <img src={p.image} alt={p.name_ar} className="mb-3 aspect-square w-full rounded-md object-cover" />
                            ) : (
                                <div className="mb-3 flex aspect-square items-center justify-center rounded-md bg-[#f1ede7] text-4xl">
                                    🌴
                                </div>
                            )}
                            <h3 className="line-clamp-2 text-sm font-semibold">{p.name_ar}</h3>
                            <div className="mt-2 flex items-center gap-2">
                                {p.on_sale ? (
                                    <>
                                        <span className="font-bold text-[#2f4f4f]">{p.effective_price} ر.س</span>
                                        <span className="text-xs text-gray-400 line-through">{p.price} ر.س</span>
                                    </>
                                ) : (
                                    <span className="font-bold text-[#2f4f4f]">{p.price} ر.س</span>
                                )}
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </StoreLayout>
    );
}
