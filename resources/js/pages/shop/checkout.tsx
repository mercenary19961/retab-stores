import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import StoreLayout from '@/layouts/store-layout';

interface Item {
    id: number;
    name_ar: string;
    quantity: number;
    line_total: number;
}

const COUNTRY_NAMES: Record<string, string> = {
    SA: 'السعودية',
    AE: 'الإمارات',
    KW: 'الكويت',
    QA: 'قطر',
    BH: 'البحرين',
    OM: 'عُمان',
};

const METHODS: { value: string; label: string }[] = [
    { value: 'bank_transfer', label: 'تحويل بنكي' },
    { value: 'card', label: 'بطاقة (مدى / فيزا / ماستركارد / Apple Pay)' },
    { value: 'tamara', label: 'تمارا — قسّمها على دفعات' },
];

export default function Checkout({
    items,
    subtotal,
    shippingFee,
    countries,
}: {
    items: Item[];
    subtotal: number;
    shippingFee: number;
    countries: string[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        country: countries[0] ?? 'SA',
        city: '',
        district: '',
        street: '',
        building: '',
        payment_method: 'bank_transfer',
        coupon_code: '',
    });

    const total = subtotal + shippingFee;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/checkout');
    };

    const field = (name: keyof typeof data, label: string, required = false, type = 'text') => (
        <label className="block">
            <span className="text-sm text-gray-600">
                {label}
                {required && <span className="text-red-500"> *</span>}
            </span>
            <input
                type={type}
                value={data[name]}
                onChange={(e) => setData(name, e.target.value)}
                className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
            />
            {errors[name] && <span className="text-xs text-red-500">{errors[name]}</span>}
        </label>
    );

    return (
        <StoreLayout>
            <Head title="إتمام الطلب" />
            <h1 className="mb-6 text-2xl font-bold">إتمام الطلب</h1>

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="rounded-lg border border-gray-200 bg-white p-4">
                        <h2 className="mb-3 font-bold">بيانات العميل</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {field('customer_name', 'الاسم', true)}
                            {field('customer_phone', 'رقم الجوال', true)}
                            {field('customer_email', 'البريد الإلكتروني (اختياري)', false, 'email')}
                        </div>
                    </section>

                    <section className="rounded-lg border border-gray-200 bg-white p-4">
                        <h2 className="mb-3 font-bold">عنوان الشحن</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="block">
                                <span className="text-sm text-gray-600">الدولة *</span>
                                <select
                                    value={data.country}
                                    onChange={(e) => setData('country', e.target.value)}
                                    className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                                >
                                    {countries.map((c) => (
                                        <option key={c} value={c}>{COUNTRY_NAMES[c] ?? c}</option>
                                    ))}
                                </select>
                            </label>
                            {field('city', 'المدينة', true)}
                            {field('district', 'الحي')}
                            {field('street', 'الشارع')}
                            {field('building', 'المبنى')}
                        </div>
                    </section>

                    <section className="rounded-lg border border-gray-200 bg-white p-4">
                        <h2 className="mb-3 font-bold">طريقة الدفع</h2>
                        <div className="space-y-2">
                            {METHODS.map((m) => (
                                <label key={m.value} className="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value={m.value}
                                        checked={data.payment_method === m.value}
                                        onChange={(e) => setData('payment_method', e.target.value)}
                                    />
                                    <span>{m.label}</span>
                                </label>
                            ))}
                        </div>
                    </section>
                </div>

                <div className="h-fit rounded-lg border border-gray-200 bg-white p-4">
                    <h2 className="mb-3 font-bold">ملخص الطلب</h2>
                    <ul className="space-y-1 text-sm">
                        {items.map((it) => (
                            <li key={it.id} className="flex justify-between">
                                <span>{it.name_ar} × {it.quantity}</span>
                                <span>{it.line_total} ر.س</span>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-3 border-t pt-3 text-sm">
                        <div className="flex justify-between"><span>المجموع الفرعي</span><span>{subtotal} ر.س</span></div>
                        <div className="flex justify-between"><span>الشحن</span><span>{shippingFee} ر.س</span></div>
                        <div className="mt-2 flex justify-between text-lg font-bold"><span>الإجمالي</span><span>{total} ر.س</span></div>
                    </div>
                    <label className="mt-3 block">
                        <span className="text-sm text-gray-600">كود الخصم</span>
                        <input
                            value={data.coupon_code}
                            onChange={(e) => setData('coupon_code', e.target.value)}
                            className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                        />
                    </label>
                    <button
                        type="submit"
                        disabled={processing}
                        className="mt-4 w-full rounded-lg bg-[#2f4f4f] px-6 py-3 font-semibold text-white transition hover:bg-[#264141] disabled:opacity-60"
                    >
                        تأكيد الطلب
                    </button>
                </div>
            </form>
        </StoreLayout>
    );
}
