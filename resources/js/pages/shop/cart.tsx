import ProductCard, { type StoreProduct } from '@/components/store/product-card';
import StoreLayout from '@/layouts/store-layout';
import { useLocalized } from '@/lib/localize';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Minus, Plus, ShoppingBag, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

interface CartItem {
    id: number;
    product_id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    image: string | null;
    unit_price: number;
    quantity: number;
    line_total: number;
}

interface Props {
    items: CartItem[];
    count: number;
    subtotal: number;
    shippingFee: number;
    freeShipping: boolean;
    discount: number;
    total: number;
    coupon: { code: string; waives_shipping: boolean } | null;
    couponError: string | null;
    bestSellers: StoreProduct[];
}

/** Money with two decimals — cart totals should never render as bare integers. */
const money = (v: number) => v.toFixed(2);

/**
 * Quantity stepper. Mirrors the product page's pill so the two read as the same
 * control, but writes straight to the server.
 *
 * ⚠️ Fires on CLICK, never on keystroke. The old implementation was a number
 * input with `onChange`, which PATCHed once per character — typing "10" sent a
 * request for 1 and then 10. `busy` also blocks a second write while one is in
 * flight, so double-tapping can't queue conflicting quantities.
 */
function QtyStepper({ item }: { item: CartItem }) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);

    const set = (quantity: number) => {
        if (busy || quantity < 1 || quantity > 99) return;
        setBusy(true);
        router.patch(`/cart/items/${item.id}`, { quantity }, { preserveScroll: true, preserveState: true, onFinish: () => setBusy(false) });
    };

    return (
        <div className="border-brand-gold/30 inline-flex items-center rounded-full border bg-white">
            <button
                type="button"
                data-testid="cart-qty-decrease"
                onClick={() => set(item.quantity - 1)}
                disabled={busy || item.quantity <= 1}
                aria-label={t('cart.decreaseQty')}
                className="text-brand-teal hover:bg-brand-cream flex size-9 items-center justify-center rounded-full transition-colors disabled:opacity-30"
            >
                <Minus className="size-4" />
            </button>
            <span data-testid="cart-qty" className="font-heading text-brand-teal w-10 text-center font-bold" aria-live="polite">
                {item.quantity}
            </span>
            <button
                type="button"
                data-testid="cart-qty-increase"
                onClick={() => set(item.quantity + 1)}
                disabled={busy || item.quantity >= 99}
                aria-label={t('cart.increaseQty')}
                className="text-brand-teal hover:bg-brand-cream flex size-9 items-center justify-center rounded-full transition-colors disabled:opacity-30"
            >
                <Plus className="size-4" />
            </button>
        </div>
    );
}

/** Coupon box. Applying only PREVIEWS — redemption happens at checkout. */
function CouponBox({ coupon, error }: { coupon: Props['coupon']; error: string | null }) {
    const { t } = useTranslation();
    const [code, setCode] = useState('');
    const [busy, setBusy] = useState(false);

    if (coupon) {
        return (
            <div className="border-brand-gold/20 bg-brand-cream/40 mt-4 rounded-xl border p-3">
                <div className="flex items-center justify-between gap-2">
                    <div className="min-w-0">
                        {/* Codes are Latin identifiers — pin LTR so RTL can't reorder them. */}
                        <p className="font-heading text-brand-teal truncate font-bold" dir="ltr" style={{ unicodeBidi: 'embed' }}>
                            {coupon.code}
                        </p>
                        {coupon.waives_shipping && <p className="text-xs text-green-700">{t('cart.couponFreeShipping')}</p>}
                    </div>
                    <button
                        type="button"
                        data-testid="cart-coupon-remove"
                        onClick={() => {
                            setBusy(true);
                            router.delete('/cart/coupon', { preserveScroll: true, onFinish: () => setBusy(false) });
                        }}
                        disabled={busy}
                        className="inline-flex items-center gap-1 text-xs text-gray-500 transition-colors hover:text-red-600 disabled:opacity-50"
                    >
                        <X className="size-3.5" />
                        {t('cart.couponRemove')}
                    </button>
                </div>
            </div>
        );
    }

    return (
        <form
            className="mt-4"
            onSubmit={(e) => {
                e.preventDefault();
                if (!code.trim() || busy) return;
                setBusy(true);
                router.post('/cart/coupon', { code }, { preserveScroll: true, onFinish: () => setBusy(false) });
            }}
        >
            <label className="mb-1.5 block text-xs text-gray-600">{t('cart.couponTitle')}</label>
            <div className="flex gap-2">
                <input
                    type="text"
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    data-testid="cart-coupon-input"
                    placeholder={t('cart.couponPlaceholder')}
                    dir="ltr"
                    className="focus:border-brand-gold min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none"
                />
                <button
                    type="submit"
                    data-testid="cart-coupon-apply"
                    disabled={busy || !code.trim()}
                    className="border-brand-teal text-brand-teal hover:bg-brand-teal shrink-0 rounded-lg border px-4 py-2 text-sm font-semibold transition-colors hover:text-white disabled:opacity-40"
                >
                    {t('cart.couponApply')}
                </button>
            </div>
            {error && <p className="mt-1.5 text-xs text-red-600">{error}</p>}
        </form>
    );
}

export default function Cart({ items, subtotal, shippingFee, freeShipping, discount, total, coupon, couponError, bestSellers }: Props) {
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');
    const hasOffers = Boolean((usePage().props as { hasOffers?: boolean }).hasOffers);

    if (items.length === 0) {
        return (
            <StoreLayout>
                <Head title={t('cart.title')} />

                <div className="mx-auto max-w-md py-10 text-center">
                    <div className="bg-brand-cream mx-auto flex size-24 items-center justify-center rounded-full">
                        <ShoppingBag className="text-brand-gold size-10" />
                    </div>
                    <h1 className="font-heading text-brand-teal mt-6 text-2xl font-bold">{t('cart.emptyTitle')}</h1>
                    <p className="mt-2 text-sm text-gray-600">{t('cart.emptyBody')}</p>

                    <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <Link
                            href="/shop"
                            className="bg-brand-teal hover:bg-brand-teal/90 inline-flex items-center gap-2 rounded-full px-6 py-3 font-semibold text-white transition-colors"
                        >
                            <ShoppingBag className="size-4" />
                            {t('cart.emptyBrowse')}
                        </Link>
                        {/* Only when something is actually discounted — `hasOffers` is a
                            shared prop, so this can't advertise an empty Offers page. */}
                        {hasOffers && (
                            <Link
                                href="/shop?on_sale=1"
                                className="border-brand-gold/40 text-brand-gold hover:bg-brand-cream rounded-full border px-6 py-3 font-semibold transition-colors"
                            >
                                {t('cart.emptyOffers')}
                            </Link>
                        )}
                    </div>
                </div>

                {/* An empty cart is otherwise a dead end — give it a way back in. */}
                {bestSellers.length > 0 && (
                    <section className="mt-4">
                        <h2 className="font-heading text-brand-teal mb-5 text-center text-xl font-bold">{t('cart.emptyBestSellers')}</h2>
                        <div className="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                            {bestSellers.map((p) => (
                                <ProductCard key={p.id} product={p} />
                            ))}
                        </div>
                    </section>
                )}
            </StoreLayout>
        );
    }

    return (
        <StoreLayout>
            <Head title={t('cart.title')} />

            <h1 className="font-heading text-brand-teal mb-6 text-2xl font-bold">{t('cart.title')}</h1>

            <div className="grid items-start gap-6 lg:grid-cols-3">
                {/* Line items */}
                <div className="space-y-3 lg:col-span-2">
                    {items.map((item) => (
                        <div
                            key={item.id}
                            data-testid="cart-item"
                            className="border-brand-gold/15 flex gap-4 rounded-xl border bg-white p-3 sm:items-center sm:p-4"
                        >
                            {/* Larger, real product image (card variant ≈15 KB), tappable. */}
                            <Link
                                href={`/products/${item.slug}`}
                                className="bg-brand-cream/60 size-24 shrink-0 overflow-hidden rounded-xl sm:size-28"
                            >
                                {item.image ? (
                                    <img src={item.image} alt={localized(item, 'name')} className="size-full object-cover" loading="lazy" />
                                ) : (
                                    <span className="flex size-full items-center justify-center text-3xl">🌴</span>
                                )}
                            </Link>

                            {/* Stacks on mobile, single row from sm up. */}
                            <div className="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                                <div className="min-w-0 flex-1">
                                    <Link
                                        href={`/products/${item.slug}`}
                                        className="font-heading text-brand-teal line-clamp-2 font-bold hover:underline"
                                    >
                                        {localized(item, 'name')}
                                    </Link>
                                    <p className="mt-1 text-xs text-gray-500">
                                        {t('cart.unitPrice')}: {money(item.unit_price)} {currency}
                                    </p>
                                </div>

                                <div className="flex items-center justify-between gap-4 sm:justify-end">
                                    <QtyStepper item={item} />

                                    <div className="text-end">
                                        <p data-testid="cart-line-total" className="font-heading text-brand-teal font-bold whitespace-nowrap">
                                            {money(item.line_total)} {currency}
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        data-testid="cart-remove"
                                        onClick={() => router.delete(`/cart/items/${item.id}`, { preserveScroll: true })}
                                        aria-label={t('cart.removeItem')}
                                        title={t('cart.removeItem')}
                                        className="text-gray-400 transition-colors hover:text-red-600"
                                    >
                                        <Trash2 className="size-4.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))}

                    <Link href="/shop" className="text-brand-gold hover:text-brand-teal inline-block pt-1 text-sm transition-colors">
                        ← {t('cart.continueShopping')}
                    </Link>
                </div>

                {/* Order summary — the real arithmetic, not "shipping added later". */}
                <aside className="border-brand-gold/15 sticky top-36 h-fit rounded-xl border bg-white p-4">
                    <h2 className="font-heading text-brand-teal mb-3 font-bold">{t('cart.summaryTitle')}</h2>

                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-gray-600">{t('cart.subtotal')}</dt>
                            <dd data-testid="cart-subtotal">
                                {money(subtotal)} {currency}
                            </dd>
                        </div>

                        {discount > 0 && (
                            <div className="flex justify-between text-green-700">
                                <dt>{t('cart.discount')}</dt>
                                <dd data-testid="cart-discount">
                                    −{money(discount)} {currency}
                                </dd>
                            </div>
                        )}

                        <div className="flex justify-between">
                            <dt className="text-gray-600">{t('cart.shipping')}</dt>
                            <dd data-testid="cart-shipping">
                                {shippingFee === 0 ? (
                                    <span className="font-semibold text-green-700">{t('cart.shippingFree')}</span>
                                ) : (
                                    <>
                                        {money(shippingFee)} {currency}
                                    </>
                                )}
                            </dd>
                        </div>
                    </dl>

                    {/* Explain WHY shipping is free, so it doesn't look like a glitch. */}
                    {shippingFee === 0 ? (
                        <p className="mt-1.5 text-xs text-green-700">
                            {coupon?.waives_shipping ? t('cart.couponFreeShipping') : freeShipping ? t('cart.shippingFree') : ''}
                        </p>
                    ) : (
                        <p className="mt-1.5 text-xs text-gray-500">{t('cart.shippingFlatNote')}</p>
                    )}

                    <div className="border-brand-gold/20 mt-3 flex items-baseline justify-between border-t pt-3">
                        <span className="font-heading text-brand-teal font-bold">{t('cart.grandTotal')}</span>
                        <span data-testid="cart-total" className="font-heading text-brand-teal text-xl font-black">
                            {money(total)} {currency}
                        </span>
                    </div>
                    <p className="mt-1 text-[11px] text-gray-400">{t('cart.vatNote')}</p>

                    <CouponBox coupon={coupon} error={couponError} />

                    <Link
                        href="/checkout"
                        data-testid="cart-checkout"
                        className="bg-brand-teal hover:bg-brand-teal/90 mt-4 block rounded-full px-6 py-3 text-center font-semibold text-white transition-colors"
                    >
                        {t('cart.checkout')}
                    </Link>
                </aside>
            </div>
        </StoreLayout>
    );
}
