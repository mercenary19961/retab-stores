import StoreSelect from '@/components/store/select';
import StoreLayout from '@/layouts/store-layout';
import { useLocalized } from '@/lib/localize';
import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

interface Item {
    id: number;
    name_ar: string;
    name_en: string | null;
    quantity: number;
    line_total: number;
}

// Display order for the methods the server says are on. Kept here rather than
// taken from the server's order so the list reads the same way every time.
const METHOD_ORDER = ['bank_transfer', 'card', 'tamara'] as const;

/**
 * Scheme acceptance marks, self-hosted in public/images/payment.
 *
 * ⚠️ Self-hosted deliberately, never hotlinked: the same marks are served from
 * Zid's CDN today, and that CDN stops serving this store at cutover — the exact
 * way the product images were lost.
 *
 * `card` carries four because one Moyasar method covers all of them; bank
 * transfer has no scheme mark and deliberately shows none rather than a stand-in
 * icon that would read as a brand it isn't.
 */
const METHOD_LOGOS: Record<string, { src: string; alt: string }[]> = {
    card: [
        { src: '/images/payment/mada.png', alt: 'mada' },
        { src: '/images/payment/visa.png', alt: 'Visa' },
        { src: '/images/payment/mastercard.png', alt: 'Mastercard' },
        { src: '/images/payment/apple-pay.svg', alt: 'Apple Pay' },
    ],
    tamara: [{ src: '/images/payment/tamara.webp', alt: 'Tamara' }],
    bank_transfer: [],
};

export default function Checkout({
    items,
    subtotal,
    shippingFee,
    countries,
    appliedCoupon,
    paymentMethods,
}: {
    items: Item[];
    subtotal: number;
    shippingFee: number;
    countries: string[];
    appliedCoupon?: string | null;
    paymentMethods: string[];
}) {
    // Only what the store currently offers, managed from /admin/settings. Typed as
    // string[] rather than the literal union, or the union narrows the form's
    // payment_method field and the radio's own onChange stops type-checking.
    const methods: string[] = METHOD_ORDER.filter((m) => paymentMethods.includes(m));
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');
    // Both are rare, so their fields stay collapsed until the shopper asks.
    const [otherRecipient, setOtherRecipient] = useState(false);
    const [asCompany, setAsCompany] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        country: countries[0] ?? 'SA',
        city: '',
        district: '',
        street: '',
        building: '',
        // Delivery by default: collection is the exception, and defaulting to it
        // would quietly drop the shipping fee for shoppers who never chose it.
        fulfillment: 'delivery',
        recipient_name: '',
        recipient_phone: '',
        company_name: '',
        company_cr: '',
        company_vat: '',
        // Default to the first method still offered — bank transfer when it is on,
        // otherwise whatever leads. Hardcoding 'bank_transfer' would preselect a
        // method the store may have switched off.
        payment_method: methods[0] ?? '',
        // Carried over from the cart page so the shopper doesn't retype it. The
        // form still submits it and placeOrder re-validates under lock.
        coupon_code: appliedCoupon ?? '',
    });

    // Collection means the customer walks in, so there is no carrier to pay.
    // Display only — CheckoutService decides the figure that is actually charged.
    const collecting = data.fulfillment === 'collection';
    const effectiveShipping = collecting ? 0 : shippingFee;
    const total = subtotal + effectiveShipping;

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
                data-testid={name}
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

                    {/* Delivery or collection. Chosen before the address block,
                        because it decides whether that block is asked for at all. */}
                    <section className="rounded-lg border border-gray-200 bg-white p-4">
                        <h2 className="mb-3 font-bold">{t('checkout.fulfillment')}</h2>
                        <div className="space-y-2">
                            {(['delivery', 'collection'] as const).map((value) => (
                                <label key={value} className="flex items-start gap-2">
                                    <input
                                        type="radio"
                                        name="fulfillment"
                                        value={value}
                                        data-testid={`fulfillment-${value}`}
                                        checked={data.fulfillment === value}
                                        onChange={(e) => setData('fulfillment', e.target.value)}
                                        className="mt-1"
                                    />
                                    <span className="min-w-0">
                                        <span className="block text-sm font-medium">{t(`checkout.fulfillmentOptions.${value}.label`)}</span>
                                        <span className="block text-xs text-gray-500">{t(`checkout.fulfillmentOptions.${value}.hint`)}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        {collecting && (
                            // The single Al Malqa shop. Hardcoded copy rather than a
                            // setting: it is the same address the Branches page and
                            // the OTO sender location already carry.
                            <p className="mt-3 rounded bg-gray-50 p-3 text-xs text-gray-600">{t('checkout.collectionAddress')}</p>
                        )}
                    </section>

                    {/* Address is for delivery only — a collection order has nowhere
                        to ship to, so asking for a city would be asking for nothing. */}
                    {!collecting && (
                        <section className="rounded-lg border border-gray-200 bg-white p-4">
                            <h2 className="mb-3 font-bold">{t('checkout.shippingAddress')}</h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="block">
                                    <span className="text-sm text-gray-600">{t('checkout.country')} *</span>
                                    <StoreSelect
                                        value={data.country}
                                        onValueChange={(v) => setData('country', v)}
                                        ariaLabel={t('checkout.country')}
                                        options={countries.map((c) => ({ value: c, label: t(`countries.${c}`) }))}
                                        triggerClassName="mt-1 w-full justify-between rounded border-gray-300 px-3 font-normal text-gray-900 hover:bg-white"
                                    />
                                </label>
                                {field('city', t('checkout.city'), true)}
                                {field('district', t('checkout.district'))}
                                {field('street', t('checkout.street'))}
                                {field('building', t('checkout.building'))}
                            </div>
                        </section>
                    )}

                    {/* Someone else receiving it, and buying as a company. Both are
                        the exception, so both stay collapsed until asked for —
                        otherwise every shopper pays the cost of two rare cases. */}
                    <section className="rounded-lg border border-gray-200 bg-white p-4">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                data-testid="checkout-other-recipient"
                                checked={otherRecipient}
                                onChange={(e) => {
                                    setOtherRecipient(e.target.checked);
                                    if (!e.target.checked) {
                                        // Clear on collapse, or hidden values would be
                                        // submitted by a shopper who changed their mind.
                                        setData('recipient_name', '');
                                        setData('recipient_phone', '');
                                    }
                                }}
                            />
                            <span className="text-sm font-medium">{t('checkout.otherRecipient')}</span>
                        </label>
                        {otherRecipient && (
                            <div className="mt-3 grid gap-4 sm:grid-cols-2">
                                {field('recipient_name', t('checkout.recipientName'), true)}
                                {field('recipient_phone', t('checkout.recipientPhone'), true)}
                            </div>
                        )}
                    </section>

                    <section className="rounded-lg border border-gray-200 bg-white p-4">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                data-testid="checkout-as-company"
                                checked={asCompany}
                                onChange={(e) => {
                                    setAsCompany(e.target.checked);
                                    if (!e.target.checked) {
                                        setData('company_name', '');
                                        setData('company_cr', '');
                                        setData('company_vat', '');
                                    }
                                }}
                            />
                            <span className="text-sm font-medium">{t('checkout.asCompany')}</span>
                        </label>
                        {asCompany && (
                            <div className="mt-3 grid gap-4 sm:grid-cols-2">
                                {field('company_name', t('checkout.companyName'), true)}
                                {/* Registration numbers are Latin digits — pinned LTR
                                    so RTL cannot reorder them mid-number. */}
                                {field('company_cr', t('checkout.companyCr'), true)}
                                {field('company_vat', t('checkout.companyVat'), true)}
                            </div>
                        )}
                    </section>

                    <section className="rounded-lg border border-gray-200 bg-white p-4">
                        <h2 className="mb-3 font-bold">{t('checkout.paymentMethod')}</h2>
                        <div className="space-y-2">
                            {methods.map((value) => {
                                const logos = METHOD_LOGOS[value] ?? [];

                                return (
                                    <label key={value} className="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value={value}
                                            checked={data.payment_method === value}
                                            onChange={(e) => setData('payment_method', e.target.value)}
                                        />
                                        <span>{t(`payment.${value}`)}</span>
                                        {logos.length > 0 && (
                                            // `ms-auto` so the marks sit at the row's far end in
                                            // both reading directions.
                                            <span className="ms-auto flex shrink-0 items-center gap-1.5">
                                                {logos.map((logo) => (
                                                    <img
                                                        key={logo.src}
                                                        src={logo.src}
                                                        alt={logo.alt}
                                                        loading="lazy"
                                                        // One shared height, width follows each mark's
                                                        // own aspect — they range from square badges
                                                        // (Visa) to wide wordmarks (mada).
                                                        className="h-5 w-auto"
                                                    />
                                                ))}
                                            </span>
                                        )}
                                    </label>
                                );
                            })}
                        </div>
                    </section>
                </div>

                <div className="h-fit rounded-lg border border-gray-200 bg-white p-4">
                    <h2 className="mb-3 font-bold">{t('checkout.orderSummary')}</h2>
                    <ul className="space-y-1 text-sm">
                        {items.map((it) => (
                            <li key={it.id} className="flex justify-between">
                                <span>
                                    {localized(it, 'name')} × {it.quantity}
                                </span>
                                <span>
                                    {it.line_total} {currency}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-3 border-t pt-3 text-sm">
                        <div className="flex justify-between">
                            <span>{t('checkout.subtotal')}</span>
                            <span>
                                {subtotal} {currency}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span>{t('checkout.shipping')}</span>
                            <span>
                                {effectiveShipping} {currency}
                            </span>
                        </div>
                        <div className="mt-2 flex justify-between text-lg font-bold">
                            <span>{t('checkout.total')}</span>
                            <span>
                                {total} {currency}
                            </span>
                        </div>
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
                        data-testid="place-order"
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
