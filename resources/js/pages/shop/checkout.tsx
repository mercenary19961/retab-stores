import GoogleLoginButton from '@/components/auth/google-login-button';
import WhatsAppLoginButton from '@/components/auth/whatsapp-login-button';
import SaudiPhoneField from '@/components/store/saudi-phone-field';
import StoreSelect from '@/components/store/select';
import StoreLayout from '@/layouts/store-layout';
import { useLocalized } from '@/lib/localize';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
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
/**
 * Where a customer who does not know their national address can get one.
 *
 * 🔴 BOTH of these belong to SAUDI POST (SPL), NOT to Retab. The WhatsApp number
 * is SPL's own published National Address channel — their site lists 0112898888
 * for exactly this enquiry, alongside Absher, Tawakkalna and the Maha chatbot
 * (their domestic support line is the separate 19992). **Do not "correct" it to
 * the store's number**: it would send customers asking about a government
 * address registry into Retab's support queue, which cannot answer them.
 *
 * The same pairing is what the live Zid store does, so customers who have
 * ordered before will recognise it.
 */
const SPL_NATIONAL_ADDRESS: Record<string, string> = {
    ar: 'https://splonline.com.sa/ar/national-address-1/',
    en: 'https://splonline.com.sa/en/national-address-1/',
};
const SPL_WHATSAPP = 'https://api.whatsapp.com/send/?phone=966112898888';

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

/** An address the customer has saved before, ready to reuse. */
interface SavedAddress {
    id: number;
    label: string | null;
    summary: string;
    is_default: boolean;
    country: string | null;
    city: string | null;
    district: string | null;
    street: string | null;
    building: string | null;
    short_address: string | null;
    phone: string | null;
}

export default function Checkout({
    items,
    subtotal,
    shippingFee,
    countries,
    appliedCoupon,
    paymentMethods,
    emailRequired = false,
    savedAddresses = [],
}: {
    items: Item[];
    subtotal: number;
    shippingFee: number;
    countries: string[];
    appliedCoupon?: string | null;
    paymentMethods: string[];
    emailRequired?: boolean;
    savedAddresses?: SavedAddress[];
}) {
    const { auth } = usePage<SharedData>().props;
    const user = auth?.user ?? null;
    // Only what the store currently offers, managed from /admin/settings. Typed as
    // string[] rather than the literal union, or the union narrows the form's
    // payment_method field and the radio's own onChange stops type-checking.
    const methods: string[] = METHOD_ORDER.filter((m) => paymentMethods.includes(m));
    const { t, i18n } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');
    // Both are rare, so their fields stay collapsed until the shopper asks.
    const [otherRecipient, setOtherRecipient] = useState(false);
    const [asCompany, setAsCompany] = useState(false);

    // A saved address to start from: their default, else the most recent. Picking
    // one is what makes a returning customer's checkout a confirmation rather
    // than a retype.
    /** A country we ship to, falling back to the first (and today only) one. */
    const shippable = (country?: string | null) => (country && countries.includes(country) ? country : (countries[0] ?? 'SA'));

    const initialAddress = savedAddresses[0] ?? null;

    const { data, setData, post, processing, errors } = useForm({
        // Prefilled from the account where we have it — signing in with Google
        // gives us a name and email, WhatsApp gives us a phone.
        customer_name: user?.name ?? '',
        customer_email: user?.email ?? '',
        customer_phone: user?.phone ?? '',
        // ⚠️ Clamped to a destination we actually ship to. A saved address from
        // before the Saudi-only change can carry 'AE', and posting that would fail
        // validation on a field the customer can no longer even see.
        country: shippable(initialAddress?.country),
        city: initialAddress?.city ?? '',
        district: initialAddress?.district ?? '',
        street: initialAddress?.street ?? '',
        building: initialAddress?.building ?? '',
        short_address: initialAddress?.short_address ?? '',
        // Only offered to signed-in customers: there is no account to save onto
        // for a guest. Defaults on, since a saved address is the point.
        //
        // ⚠️ '1'/'0' rather than a real boolean, so every value in this form stays
        // a string. One boolean among strings widens useForm's inferred value type
        // to a union, and every `data[name]` read in the field() helper below then
        // stops type-checking. Same convention as admin_help_pulse.
        save_address: '1',
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

    /**
     * 🔑 The gate for everything below the identity block.
     *
     * A phone OR an email is enough — one contactable channel is what turns an
     * anonymous cart into someone we can reach about the order. Signing in
     * satisfies it outright.
     *
     * Deliberately NOT a strict format check: revealing the rest of the form the
     * moment someone mistypes a digit, then hiding it again, is worse than
     * showing it a keystroke early. The server validates properly on submit.
     */
    const contactable = data.customer_phone.trim().length >= 6 || data.customer_email.includes('@');

    // ⚠️ When an email is required, a phone alone no longer opens the rest of the
    // form: revealing it and then refusing the submit for a field they were told
    // was optional is the failure this gate exists to avoid. A signed-in customer
    // with an address on file already satisfies it.
    const identified = emailRequired ? data.customer_email.includes('@') || Boolean(user?.email) : Boolean(user) || contactable;

    // Which saved address is selected, or null while typing a new one.
    const [addressId, setAddressId] = useState<number | null>(initialAddress?.id ?? null);

    const applyAddress = (a: SavedAddress) => {
        setAddressId(a.id);
        setData((d) => ({
            ...d,
            country: shippable(a.country),
            city: a.city ?? '',
            district: a.district ?? '',
            street: a.street ?? '',
            building: a.building ?? '',
            short_address: a.short_address ?? '',
        }));
    };

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
                    {/* Step 1 — who are you. Nothing below this appears until we
                        have a way to reach them, so a guest sees a short form
                        instead of every field in the checkout at once. */}
                    <section className="rounded-lg border border-gray-200 bg-white p-4">
                        <h2 className="mb-1 font-bold">{t('checkout.identity.title')}</h2>
                        <p className="mb-4 text-sm text-gray-500">{t('checkout.identity.hint')}</p>

                        {user ? (
                            // Already signed in: say so rather than showing sign-in
                            // buttons that would only confuse.
                            <p className="rounded bg-gray-50 p-3 text-sm text-gray-700">
                                {t('checkout.identity.signedInAs', { name: user.name || user.email || user.phone })}
                            </p>
                        ) : (
                            <>
                                {/* Each renders nothing when its provider is not
                                    configured, so no dead buttons appear here. */}
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <GoogleLoginButton />
                                    <WhatsAppLoginButton />
                                </div>

                                <div className="my-4 flex items-center gap-3 text-xs text-gray-400">
                                    <span className="h-px flex-1 bg-gray-200" />
                                    {t('checkout.identity.or')}
                                    <span className="h-px flex-1 bg-gray-200" />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <SaudiPhoneField
                                        label={t('checkout.phone')}
                                        value={data.customer_phone}
                                        onChange={(v) => setData('customer_phone', v)}
                                        error={errors.customer_phone}
                                    />
                                    {field(
                                        'customer_email',
                                        emailRequired ? t('checkout.email') : t('checkout.emailOptional'),
                                        emailRequired,
                                        'email',
                                    )}
                                </div>
                            </>
                        )}
                    </section>

                    {/* Everything below is revealed once we can reach them. */}
                    {identified && (
                        <section className="rounded-lg border border-gray-200 bg-white p-4">
                            <h2 className="mb-3 font-bold">{t('checkout.customerInfo')}</h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {field('customer_name', t('checkout.name'), true)}
                                {/* Shown again for a signed-in customer, whose phone
                                    came from the account and may need correcting for
                                    this delivery. */}
                                {user && (
                                    <SaudiPhoneField
                                        label={t('checkout.phone')}
                                        value={data.customer_phone}
                                        onChange={(v) => setData('customer_phone', v)}
                                        error={errors.customer_phone}
                                    />
                                )}
                            </div>
                        </section>
                    )}

                    {/* Delivery or collection. Chosen before the address block,
                        because it decides whether that block is asked for at all. */}
                    {identified && (
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
                    )}

                    {/* Address is for delivery only — a collection order has nowhere
                        to ship to, so asking for a city would be asking for nothing. */}
                    {identified && !collecting && (
                        <section className="rounded-lg border border-gray-200 bg-white p-4">
                            <h2 className="mb-3 font-bold">{t('checkout.shippingAddress')}</h2>

                            {/* Addresses this customer has used before. Picking one is
                                the whole point of saving them — a returning shopper
                                confirms instead of retyping. */}
                            {savedAddresses.length > 0 && (
                                <div className="mb-4 space-y-2">
                                    {savedAddresses.map((a) => (
                                        <label key={a.id} className="flex items-start gap-2 rounded border border-gray-200 p-3">
                                            <input
                                                type="radio"
                                                name="saved_address"
                                                checked={addressId === a.id}
                                                onChange={() => applyAddress(a)}
                                                className="mt-1"
                                            />
                                            <span className="min-w-0">
                                                {a.label && <span className="block text-sm font-medium">{a.label}</span>}
                                                <span className="block text-sm text-gray-700">{a.summary}</span>
                                            </span>
                                        </label>
                                    ))}
                                    <label className="flex items-center gap-2 rounded border border-dashed border-gray-300 p-3">
                                        <input type="radio" name="saved_address" checked={addressId === null} onChange={() => setAddressId(null)} />
                                        <span className="text-sm font-medium">{t('checkout.addAnotherAddress')}</span>
                                    </label>
                                </div>
                            )}

                            <div className="grid gap-4 sm:grid-cols-2">
                                {/* 🔑 A picker with one option is not a choice, it is a
                                    chore. The store ships to Saudi Arabia only, so the
                                    single destination is stated rather than offered —
                                    and this reads off `countries`, so re-opening a
                                    country brings the real picker back with no edit
                                    here. */}
                                <label className="block">
                                    <span className="text-sm text-gray-600">{t('checkout.country')} *</span>
                                    {countries.length > 1 ? (
                                        <StoreSelect
                                            value={data.country}
                                            onValueChange={(v) => setData('country', v)}
                                            ariaLabel={t('checkout.country')}
                                            options={countries.map((c) => ({ value: c, label: t(`countries.${c}`) }))}
                                            triggerClassName="mt-1 w-full justify-between rounded border-gray-300 px-3 font-normal text-gray-900 hover:bg-white"
                                        />
                                    ) : (
                                        <span className="mt-1 block rounded border border-gray-200 bg-gray-50 px-3 py-2 text-gray-700">
                                            {t(`countries.${data.country}`)}
                                        </span>
                                    )}
                                </label>
                                {field('city', t('checkout.city'), true)}
                                {field('district', t('checkout.district'))}
                                {field('street', t('checkout.street'))}
                                {field('building', t('checkout.building'))}
                                {/* Saudi National Address short code — what couriers
                                    actually navigate by. Optional: plenty of customers
                                    do not know theirs, and the street already works. */}
                                <label className="block">
                                    <span className="text-sm text-gray-600">{t('checkout.shortAddress')}</span>
                                    <input
                                        type="text"
                                        data-testid="short_address"
                                        value={data.short_address}
                                        onChange={(e) => setData('short_address', e.target.value.toUpperCase())}
                                        placeholder="RRMD7708"
                                        // Latin letters and digits: pin LTR so RTL cannot
                                        // reorder the code mid-value.
                                        dir="ltr"
                                        maxLength={12}
                                        className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                                    />
                                    <span className="mt-1 block text-xs text-gray-400">{t('checkout.shortAddressHint')}</span>
                                    {/* The way out for the many customers who do not
                                        know their code. Without it the hint describes
                                        a value with nowhere to go and get it.
                                        ⚠️ `target="_blank"` is not optional here: this
                                        is a checkout, and navigating away from a filled
                                        form to look something up loses the order. */}
                                    <span className="mt-1 block text-xs text-gray-500">
                                        {t('checkout.shortAddressUnknown')}{' '}
                                        <a
                                            href={SPL_NATIONAL_ADDRESS[i18n.language] ?? SPL_NATIONAL_ADDRESS.ar}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-brand-teal underline underline-offset-2"
                                        >
                                            {t('checkout.shortAddressLookup')}
                                        </a>
                                        <span aria-hidden> · </span>
                                        <a
                                            href={SPL_WHATSAPP}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-brand-teal underline underline-offset-2"
                                        >
                                            {t('checkout.shortAddressAsk')}
                                        </a>
                                    </span>
                                    {errors.short_address && <span className="text-xs text-red-500">{errors.short_address}</span>}
                                </label>
                            </div>

                            {/* Only a signed-in customer has an account to save onto. */}
                            {user && (
                                <label className="mt-4 flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        data-testid="save_address"
                                        checked={data.save_address === '1'}
                                        onChange={(e) => setData('save_address', e.target.checked ? '1' : '0')}
                                    />
                                    <span className="text-sm">{t('checkout.saveAddress')}</span>
                                </label>
                            )}
                        </section>
                    )}

                    {/* Someone else receiving it, and buying as a company. Both are
                        the exception, so both stay collapsed until asked for —
                        otherwise every shopper pays the cost of two rare cases. */}
                    {identified && (
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
                    )}

                    {identified && (
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
                    )}

                    {identified && (
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
                    )}
                </div>

                {/* Follows the shopper down the page: the total and the confirm
                    button are what they are deciding on, and a long form pushed
                    both off screen. `h-fit` is what lets it stick inside a grid
                    column — a stretched item has nothing to scroll within.

                    Sticky only from `lg`: below that the summary is stacked above
                    the form, where pinning it would eat a phone's viewport. The
                    offset clears the sticky header. */}
                <div className="h-fit rounded-lg border border-gray-200 bg-white p-4 lg:sticky lg:top-32">
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
