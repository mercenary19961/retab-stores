import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocalized } from '@/lib/localize';
import StoreLayout from '@/layouts/store-layout';

interface Item {
    id: number;
    name_ar: string;
    name_en: string | null;
    quantity: number;
    line_total: number;
}

const METHOD_VALUES = ['bank_transfer', 'card', 'tamara'] as const;

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
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');

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
            <Head title={t('checkout.title')} />
            <h1 className="mb-6 text-2xl font-bold">{t('checkout.title')}</h1>

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="rounded-lg border border-gray-200 bg-white p-4">
                        <h2 className="mb-3 font-bold">{t('checkout.customerInfo')}</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {field('customer_name', t('checkout.name'), true)}
                            {field('customer_phone', t('checkout.phone'), true)}
                            {field('customer_email', t('checkout.emailOptional'), false, 'email')}
                        </div>
                    </section>

                    <section className="rounded-lg border border-gray-200 bg-white p-4">
                        <h2 className="mb-3 font-bold">{t('checkout.shippingAddress')}</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="block">
                                <span className="text-sm text-gray-600">{t('checkout.country')} *</span>
                                <select
                                    value={data.country}
                                    onChange={(e) => setData('country', e.target.value)}
                                    className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                                >
                                    {countries.map((c) => (
                                        <option key={c} value={c}>{t(`countries.${c}`)}</option>
                                    ))}
                                </select>
                            </label>
                            {field('city', t('checkout.city'), true)}
                            {field('district', t('checkout.district'))}
                            {field('street', t('checkout.street'))}
                            {field('building', t('checkout.building'))}
                        </div>
                    </section>

                    <section className="rounded-lg border border-gray-200 bg-white p-4">
                        <h2 className="mb-3 font-bold">{t('checkout.paymentMethod')}</h2>
                        <div className="space-y-2">
                            {METHOD_VALUES.map((value) => (
                                <label key={value} className="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value={value}
                                        checked={data.payment_method === value}
                                        onChange={(e) => setData('payment_method', e.target.value)}
                                    />
                                    <span>{t(`payment.${value}`)}</span>
                                </label>
                            ))}
                        </div>
                    </section>
                </div>

                <div className="h-fit rounded-lg border border-gray-200 bg-white p-4">
                    <h2 className="mb-3 font-bold">{t('checkout.orderSummary')}</h2>
                    <ul className="space-y-1 text-sm">
                        {items.map((it) => (
                            <li key={it.id} className="flex justify-between">
                                <span>{localized(it, 'name')} × {it.quantity}</span>
                                <span>{it.line_total} {currency}</span>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-3 border-t pt-3 text-sm">
                        <div className="flex justify-between"><span>{t('checkout.subtotal')}</span><span>{subtotal} {currency}</span></div>
                        <div className="flex justify-between"><span>{t('checkout.shipping')}</span><span>{shippingFee} {currency}</span></div>
                        <div className="mt-2 flex justify-between text-lg font-bold"><span>{t('checkout.total')}</span><span>{total} {currency}</span></div>
                    </div>
                    <label className="mt-3 block">
                        <span className="text-sm text-gray-600">{t('checkout.couponCode')}</span>
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
                        {t('checkout.confirm')}
                    </button>
                </div>
            </form>
        </StoreLayout>
    );
}
