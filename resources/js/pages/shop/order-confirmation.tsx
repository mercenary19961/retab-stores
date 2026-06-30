import { Head, Link } from '@inertiajs/react';
import StoreLayout from '@/layouts/store-layout';

interface Order {
    order_number: string;
    status: string;
    payment_status: string;
    payment_method: string | null;
    total: number;
}

interface Bank {
    bank_name: string;
    beneficiary: string;
    account: string;
    iban: string;
}

export default function OrderConfirmation({ order, bank }: { order: Order; bank: Bank | null }) {
    return (
        <StoreLayout>
            <Head title={`طلب ${order.order_number}`} />

            <div className="mx-auto max-w-xl text-center">
                <div className="text-5xl">✅</div>
                <h1 className="mt-3 text-2xl font-bold">تم استلام طلبك</h1>
                <p className="mt-2 text-gray-600">
                    رقم الطلب: <span className="font-mono font-semibold">{order.order_number}</span>
                </p>
                <p className="mt-1 text-gray-600">الإجمالي: {order.total} ر.س</p>

                {bank ? (
                    <div className="mt-6 rounded-lg border border-gray-200 bg-white p-5 text-start">
                        <h2 className="mb-2 font-bold">للدفع عبر التحويل البنكي</h2>
                        <p className="text-sm text-gray-600">
                            حوّل المبلغ إلى الحساب التالي، واذكر <b>رقم الطلب</b> في بيان التحويل. يتم تأكيد الطلب بعد
                            التحقق من التحويل.
                        </p>
                        <dl className="mt-3 space-y-1 text-sm">
                            <div className="flex justify-between"><dt className="text-gray-500">البنك</dt><dd>{bank.bank_name}</dd></div>
                            <div className="flex justify-between"><dt className="text-gray-500">المستفيد</dt><dd>{bank.beneficiary}</dd></div>
                            <div className="flex justify-between"><dt className="text-gray-500">رقم الحساب</dt><dd className="font-mono">{bank.account}</dd></div>
                            <div className="flex justify-between"><dt className="text-gray-500">الآيبان</dt><dd className="font-mono">{bank.iban}</dd></div>
                        </dl>
                    </div>
                ) : (
                    <p className="mt-4 text-gray-600">سنقوم بمعالجة طلبك وإشعارك بالتأكيد قريباً.</p>
                )}

                <Link href="/" className="mt-6 inline-block text-[#2f4f4f] underline">
                    العودة للمتجر
                </Link>
            </div>
        </StoreLayout>
    );
}
