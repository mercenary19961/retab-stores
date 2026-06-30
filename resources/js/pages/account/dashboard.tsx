import { Head, Link, router } from '@inertiajs/react';
import StoreLayout from '@/layouts/store-layout';

interface OrderRow {
    order_number: string;
    status: string;
    payment_status: string;
    total: number;
    created_at: string | null;
}

interface Reward {
    code: string;
    value: number;
}

interface Loyalty {
    confirmed_purchases: number;
    milestone: number;
    progress: number;
    remaining: number;
    reward_percent: number;
    rewards: Reward[];
}

interface Profile {
    name: string | null;
    phone: string | null;
}

const STATUS_AR: Record<string, string> = {
    pending_payment: 'بانتظار الدفع',
    awaiting_confirmation: 'بانتظار التأكيد',
    confirmed: 'تم التأكيد',
    shipped: 'تم الشحن',
    delivered: 'تم التوصيل',
    cancelled: 'ملغي',
    unavailable: 'غير متوفر',
};

export default function AccountDashboard({
    profile,
    orders,
    loyalty,
}: {
    profile: Profile;
    orders: OrderRow[];
    loyalty: Loyalty;
}) {
    return (
        <StoreLayout>
            <Head title="حسابي" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-bold">حسابي</h1>
                <button type="button" onClick={() => router.post('/logout')} className="text-sm text-gray-500 underline">
                    تسجيل الخروج
                </button>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Loyalty */}
                <section className="rounded-lg border border-gray-200 bg-white p-5 lg:col-span-1">
                    <h2 className="mb-1 font-bold">برنامج الولاء</h2>
                    <p className="text-sm text-gray-600">
                        كل {loyalty.milestone} طلبات مؤكدة = خصم {loyalty.reward_percent}%
                    </p>

                    <div className="mt-4">
                        <div className="flex gap-1.5">
                            {Array.from({ length: loyalty.milestone }).map((_, i) => (
                                <div
                                    key={i}
                                    className={`h-2.5 flex-1 rounded-full ${i < loyalty.progress ? 'bg-[#2f4f4f]' : 'bg-gray-200'}`}
                                />
                            ))}
                        </div>
                        <p className="mt-2 text-sm text-gray-600">
                            {loyalty.confirmed_purchases} طلب مؤكد
                            {loyalty.remaining > 0 && ` — باقي ${loyalty.remaining} للمكافأة القادمة`}
                        </p>
                    </div>

                    {loyalty.rewards.length > 0 && (
                        <div className="mt-4 rounded-lg bg-green-50 p-3">
                            <p className="mb-1 text-sm font-semibold text-green-800">كوبونات مكافآت متاحة</p>
                            {loyalty.rewards.map((r) => (
                                <div key={r.code} className="flex justify-between text-sm text-green-800">
                                    <span className="font-mono">{r.code}</span>
                                    <span>{r.value}%</span>
                                </div>
                            ))}
                        </div>
                    )}

                    <Link href="/account/profile" className="mt-4 inline-block text-sm text-[#2f4f4f] underline">
                        تعديل البيانات
                    </Link>
                </section>

                {/* Orders */}
                <section className="rounded-lg border border-gray-200 bg-white p-5 lg:col-span-2">
                    <h2 className="mb-3 font-bold">طلباتي</h2>
                    {orders.length === 0 ? (
                        <p className="text-sm text-gray-400">لا توجد طلبات بعد.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="text-start text-gray-500">
                                <tr>
                                    <th className="py-2 text-start font-medium">رقم الطلب</th>
                                    <th className="py-2 text-start font-medium">الحالة</th>
                                    <th className="py-2 text-start font-medium">الإجمالي</th>
                                    <th className="py-2 text-start font-medium">التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.map((o) => (
                                    <tr key={o.order_number} className="border-t border-gray-100">
                                        <td className="py-2">
                                            <Link href={`/orders/${o.order_number}`} className="font-mono text-[#2f4f4f] underline">
                                                {o.order_number}
                                            </Link>
                                        </td>
                                        <td className="py-2">{STATUS_AR[o.status] ?? o.status}</td>
                                        <td className="py-2">{o.total} ر.س</td>
                                        <td className="py-2 text-gray-500">{o.created_at ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            </div>
        </StoreLayout>
    );
}
