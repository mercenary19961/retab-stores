import { Head, Link, router } from '@inertiajs/react';
import StoreLayout from '@/layouts/store-layout';

interface CartItem {
    id: number;
    product_id: number;
    name_ar: string;
    slug: string;
    unit_price: number;
    quantity: number;
    line_total: number;
}

export default function Cart({ items, subtotal }: { items: CartItem[]; count: number; subtotal: number }) {
    return (
        <StoreLayout>
            <Head title="سلة التسوق" />

            <h1 className="mb-6 text-2xl font-bold">سلة التسوق</h1>

            {items.length === 0 ? (
                <p className="text-gray-500">
                    سلتك فارغة.{' '}
                    <Link href="/" className="text-[#2f4f4f] underline">
                        تصفّح المنتجات
                    </Link>
                </p>
            ) : (
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-3 lg:col-span-2">
                        {items.map((item) => (
                            <div key={item.id} className="flex items-center gap-4 rounded-lg border border-gray-200 bg-white p-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded bg-[#f1ede7] text-2xl">🌴</div>

                                <div className="flex-1">
                                    <Link href={`/products/${item.slug}`} className="font-semibold hover:underline">
                                        {item.name_ar}
                                    </Link>
                                    <div className="text-sm text-gray-500">{item.unit_price} ر.س</div>
                                </div>

                                <input
                                    type="number"
                                    min={1}
                                    defaultValue={item.quantity}
                                    onChange={(e) =>
                                        router.patch(
                                            `/cart/items/${item.id}`,
                                            { quantity: Number(e.target.value) },
                                            { preserveScroll: true },
                                        )
                                    }
                                    className="w-16 rounded border border-gray-300 px-2 py-1 text-center"
                                />

                                <div className="w-20 text-start font-semibold">{item.line_total} ر.س</div>

                                <button
                                    type="button"
                                    onClick={() => router.delete(`/cart/items/${item.id}`, { preserveScroll: true })}
                                    className="text-sm text-red-500 hover:text-red-700"
                                >
                                    حذف
                                </button>
                            </div>
                        ))}
                    </div>

                    <div className="h-fit rounded-lg border border-gray-200 bg-white p-4">
                        <div className="flex justify-between text-lg font-bold">
                            <span>المجموع</span>
                            <span>{subtotal} ر.س</span>
                        </div>
                        <p className="mt-1 text-xs text-gray-500">يُضاف الشحن عند الدفع</p>
                        <Link
                            href="/checkout"
                            className="mt-4 block rounded-lg bg-[#2f4f4f] px-6 py-3 text-center font-semibold text-white transition hover:bg-[#264141]"
                        >
                            متابعة الدفع
                        </Link>
                    </div>
                </div>
            )}
        </StoreLayout>
    );
}
