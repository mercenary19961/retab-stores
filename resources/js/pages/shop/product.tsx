import CopyText from '@/components/copy-text';
import ProductGallery from '@/components/store/product-gallery';
import { Turnstile } from '@/components/turnstile';
import StoreLayout from '@/layouts/store-layout';
import { useLocalized } from '@/lib/localize';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Gift, Heart, Link2, Minus, Plus, ShoppingBag, Sparkles, Star } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Product {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    sku: string | null;
    description_ar: string | null;
    description_en: string | null;
    price: number;
    sale_price: number | null;
    effective_price: number;
    on_sale: boolean;
    in_stock: boolean;
    coming_soon: boolean;
    purchase_count: number;
    category: { name_ar: string; name_en: string | null; slug: string } | null;
    images: string[];
    images_thumb: string[];
    images_full: string[];
    url: string;
}

interface ReviewItem {
    id: number;
    rating: number;
    title: string | null;
    body: string | null;
    author: string;
    helpful_count: number;
    voted: boolean;
    is_mine: boolean;
    date: string | null;
}

interface Reviews {
    summary: { count: number; average: number };
    items: ReviewItem[];
    can_review: boolean;
}

interface ReviewReward {
    available: boolean;
    percent: number;
}

function Stars({ value, className = '' }: { value: number; className?: string }) {
    const rounded = Math.round(value);
    return (
        <span className={`inline-flex ${className}`} dir="ltr" aria-label={`${value} / 5`}>
            {[1, 2, 3, 4, 5].map((n) => (
                <Star key={n} className={`size-4 ${n <= rounded ? 'fill-brand-gold text-brand-gold' : 'fill-brand-gold/15 text-brand-gold/25'}`} />
            ))}
        </span>
    );
}

export default function ShopProduct({
    product,
    reviews,
    reviewReward,
    wishlisted,
    authed,
}: {
    product: Product;
    reviews: Reviews;
    reviewReward: ReviewReward;
    wishlisted: boolean;
    authed: boolean;
}) {
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');
    const name = localized(product, 'name');
    const description = localized(product, 'description');

    const [qty, setQty] = useState(1);
    const installment = product.effective_price / 4;

    // Product/Offer structured data for rich results.
    const jsonLd = {
        '@context': 'https://schema.org',
        '@type': 'Product',
        name,
        description: description || name,
        image: product.images,
        sku: product.sku ?? undefined,
        offers: {
            '@type': 'Offer',
            url: product.url,
            price: product.effective_price.toFixed(2),
            priceCurrency: 'SAR',
            availability: product.in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        },
    };

    return (
        <StoreLayout>
            <Head title={name}>
                <meta name="description" content={(description || name).slice(0, 160)} />
                <meta property="og:title" content={name} />
                <meta property="og:type" content="product" />
                <meta property="og:url" content={product.url} />
                {product.images[0] && <meta property="og:image" content={product.images[0]} />}
                <script type="application/ld+json">{JSON.stringify(jsonLd)}</script>
            </Head>

            <nav className="text-brand-teal/60 mb-6 text-sm">
                <Link href="/" className="hover:text-brand-teal">
                    {t('common.home')}
                </Link>
                {product.category && (
                    <>
                        <span className="text-brand-teal/30 mx-1.5">/</span>
                        <Link href={`/shop?category=${product.category.slug}`} className="hover:text-brand-teal">
                            {localized(product.category, 'name')}
                        </Link>
                    </>
                )}
            </nav>

            <div className="grid gap-8 md:grid-cols-2">
                {/* Gallery */}
                <ProductGallery
                    images={product.images}
                    imagesThumb={product.images_thumb ?? product.images}
                    imagesFull={product.images_full ?? product.images}
                    name={name}
                />

                {/* Details */}
                <div>
                    <div className="flex items-start justify-between gap-3">
                        <h1 className="font-heading text-brand-teal text-2xl font-bold sm:text-3xl">{name}</h1>
                        {authed && (
                            <button
                                type="button"
                                onClick={() => router.post(`/wishlist/${product.slug}/toggle`, {}, { preserveScroll: true })}
                                className="shrink-0 rounded-full p-1"
                                title={wishlisted ? t('product.wishlistRemove') : t('product.wishlistAdd')}
                            >
                                <Heart className={`size-6 ${wishlisted ? 'fill-red-500 text-red-500' : 'text-brand-teal/30'}`} />
                            </button>
                        )}
                    </div>

                    {product.coming_soon && (
                        <span className="bg-brand-teal/10 text-brand-teal mt-3 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold">
                            <Sparkles className="size-4" />
                            {t('product.comingSoon')}
                        </span>
                    )}

                    <div className="text-brand-teal/70 mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <button
                            type="button"
                            onClick={() => document.getElementById('reviews')?.scrollIntoView({ behavior: 'smooth' })}
                            title={t('product.rateThis')}
                            aria-label={t('product.rateThis')}
                            className="inline-flex items-center gap-1.5 rounded transition-opacity hover:opacity-75"
                        >
                            <Stars value={reviews.summary.average} />
                            {reviews.summary.count > 0 && <span className="text-brand-teal font-medium">{reviews.summary.average}</span>}
                            <span className="text-brand-teal/50">{t('product.ratingsLabel', { n: reviews.summary.count })}</span>
                        </button>
                        {product.purchase_count > 0 && (
                            <span className="text-brand-teal/50 inline-flex items-center gap-1.5">
                                <ShoppingBag className="size-4" />
                                {t('product.timesBought', { n: product.purchase_count })}
                            </span>
                        )}
                    </div>

                    {!product.coming_soon && (
                        <div className="font-heading mt-4 flex items-center gap-3">
                            {product.on_sale ? (
                                <>
                                    <span className="text-brand-teal text-3xl font-bold">
                                        {product.effective_price.toFixed(2)} {currency}
                                    </span>
                                    <span className="text-brand-teal/40 text-lg line-through">
                                        {product.price.toFixed(2)} {currency}
                                    </span>
                                </>
                            ) : (
                                <span className="text-brand-teal text-3xl font-bold">
                                    {product.price.toFixed(2)} {currency}
                                </span>
                            )}
                        </div>
                    )}

                    {/* Tamara installments (marketing estimate — no SDK) */}
                    {!product.coming_soon && product.in_stock && (
                        <div className="border-brand-gold/25 bg-brand-cream/40 mt-4 rounded-xl border p-3">
                            <span className="text-brand-teal flex flex-wrap items-center gap-2 text-sm">
                                <span className="bg-brand-teal rounded-md px-2 py-0.5 text-xs font-bold tracking-wide text-white lowercase">
                                    tamara
                                </span>
                                {t('product.tamaraSplit', { amount: installment.toFixed(2), currency })}
                            </span>
                            <p className="text-brand-teal/60 mt-1 text-xs">{t('product.tamaraNote')}</p>
                        </div>
                    )}

                    {description && <p className="text-brand-teal/80 mt-5 leading-relaxed">{description}</p>}

                    {/* Buy — or, for Coming-Soon products, register interest instead */}
                    {product.coming_soon ? (
                        <RequestSection slug={product.slug} authed={authed} />
                    ) : product.in_stock ? (
                        <div className="mt-6 flex flex-wrap items-center gap-4">
                            <div className="border-brand-gold/30 inline-flex items-center rounded-full border bg-white">
                                <button
                                    type="button"
                                    onClick={() => setQty((q) => Math.max(1, q - 1))}
                                    disabled={qty <= 1}
                                    aria-label={t('product.decreaseQty')}
                                    className="text-brand-teal flex size-10 items-center justify-center disabled:opacity-30"
                                >
                                    <Minus className="size-4" />
                                </button>
                                <span className="font-heading text-brand-teal w-10 text-center font-bold" aria-live="polite">
                                    {qty}
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setQty((q) => Math.min(99, q + 1))}
                                    disabled={qty >= 99}
                                    aria-label={t('product.increaseQty')}
                                    className="text-brand-teal flex size-10 items-center justify-center disabled:opacity-30"
                                >
                                    <Plus className="size-4" />
                                </button>
                            </div>
                            <button
                                type="button"
                                data-testid="add-to-cart"
                                onClick={() => router.post('/cart', { product_id: product.id, quantity: qty }, { preserveScroll: true })}
                                className="bg-brand-teal hover:bg-brand-teal/90 inline-flex flex-1 items-center justify-center gap-2 rounded-full px-8 py-3 font-semibold text-white transition-colors"
                            >
                                <ShoppingBag className="size-5" />
                                {t('product.addToCart')}
                            </button>
                        </div>
                    ) : (
                        <div className="mt-6">
                            <span className="bg-brand-cream text-brand-teal/50 inline-block rounded-full px-6 py-3 font-semibold">
                                {t('product.outOfStock')}
                            </span>
                        </div>
                    )}

                    {product.sku && (
                        <p className="text-brand-teal/60 mt-6 flex items-center gap-1.5 text-sm">
                            <span className="text-brand-teal/80 font-medium">{t('product.sku')}:</span>
                            <CopyText
                                value={product.sku}
                                copyLabel={t('product.copySku')}
                                copiedLabel={t('product.skuCopied')}
                                className="text-brand-teal hover:text-brand-gold transition-colors"
                            />
                        </p>
                    )}

                    <ShareRow url={product.url} name={name} />
                </div>
            </div>

            {/* Reviews */}
            <section id="reviews" className="mt-14 scroll-mt-24">
                <h2 className="font-heading text-brand-teal mb-4 text-xl font-bold">{t('product.reviewsHeading')}</h2>

                {reviewReward.available && (
                    <div className="border-brand-gold/30 bg-brand-cream/50 mb-4 flex items-start gap-3 rounded-xl border p-4">
                        <Gift className="text-brand-gold mt-0.5 size-5 shrink-0" />
                        <p className="text-brand-teal text-sm font-medium">{t('product.reviewReward', { percent: reviewReward.percent })}</p>
                    </div>
                )}

                {reviews.can_review && <ReviewForm slug={product.slug} />}

                {!authed && (
                    <p className="text-brand-teal/60 mb-6 text-sm">
                        <Link href="/login/whatsapp" className="text-brand-gold hover:text-brand-teal underline">
                            {t('product.signInLink')}
                        </Link>{' '}
                        {t('product.signInPrompt')}
                    </p>
                )}

                {reviews.items.length === 0 ? (
                    <p className="text-brand-teal/50 text-sm">{t('product.noReviews')}</p>
                ) : (
                    <ul className="space-y-4">
                        {reviews.items.map((r) => (
                            <li key={r.id} className="border-brand-gold/15 rounded-xl border bg-white p-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Stars value={r.rating} />
                                        <span className="text-brand-teal text-sm font-semibold">{r.author}</span>
                                        {r.is_mine && (
                                            <span className="bg-brand-cream text-brand-teal/70 rounded px-1.5 py-0.5 text-xs">
                                                {t('product.yourReview')}
                                            </span>
                                        )}
                                    </div>
                                    <span className="text-brand-teal/40 text-xs">{r.date}</span>
                                </div>
                                {r.title && <p className="text-brand-teal mt-2 font-semibold">{r.title}</p>}
                                {r.body && <p className="text-brand-teal/70 mt-1 text-sm leading-relaxed">{r.body}</p>}

                                {authed && !r.is_mine && (
                                    <button
                                        type="button"
                                        onClick={() => router.post(`/reviews/${r.id}/helpful`, {}, { preserveScroll: true })}
                                        className={`mt-3 text-xs ${r.voted ? 'text-brand-teal font-semibold' : 'text-brand-teal/50'}`}
                                    >
                                        {t('product.helpful', { n: r.helpful_count })}
                                    </button>
                                )}
                                {(!authed || r.is_mine) && r.helpful_count > 0 && (
                                    <span className="text-brand-teal/40 mt-3 block text-xs">{t('product.helpful', { n: r.helpful_count })}</span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </StoreLayout>
    );
}

/**
 * Coming-Soon demand capture: a customer registers interest without buying.
 * Signed-in customers are one click (their account carries the contact); guests
 * supply a phone and pass the Turnstile bot gate (which renders nothing until a
 * site key is configured, so dev/staging stays frictionless).
 */
function RequestSection({ slug, authed }: { slug: string; authed: boolean }) {
    const { t } = useTranslation();
    const [done, setDone] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ phone: '', 'cf-turnstile-response': '' });

    const submit = (e?: FormEvent) => {
        e?.preventDefault();
        post(`/products/${slug}/request`, { preserveScroll: true, onSuccess: () => setDone(true) });
    };

    if (done) {
        return (
            <div className="border-brand-teal/20 bg-brand-cream/50 text-brand-teal mt-6 flex items-center gap-2 rounded-xl border p-4 text-sm font-medium">
                <Check className="text-brand-teal size-5 shrink-0" />
                {t('product.requestThanks')}
            </div>
        );
    }

    if (authed) {
        return (
            <button
                type="button"
                onClick={() => submit()}
                disabled={processing}
                className="bg-brand-teal hover:bg-brand-teal/90 mt-6 inline-flex items-center justify-center gap-2 rounded-full px-8 py-3 font-semibold text-white transition-colors disabled:opacity-60"
            >
                <Sparkles className="size-5" />
                {t('product.requestButton')}
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="border-brand-gold/25 mt-6 space-y-3 rounded-xl border bg-white p-4">
            <p className="text-brand-teal/80 text-sm">{t('product.requestPrompt')}</p>
            <input
                value={data.phone}
                onChange={(e) => setData('phone', e.target.value)}
                inputMode="tel"
                dir="ltr"
                placeholder={t('product.phonePlaceholder')}
                className="border-brand-gold/30 text-brand-teal focus:border-brand-teal w-full rounded-lg border px-3 py-2 text-start text-sm focus:outline-none"
            />
            {errors.phone && <p className="text-xs text-red-500">{errors.phone}</p>}
            <Turnstile onVerify={(token) => setData('cf-turnstile-response', token)} />
            <button
                type="submit"
                disabled={processing}
                className="bg-brand-teal hover:bg-brand-teal/90 inline-flex items-center justify-center gap-2 rounded-full px-6 py-2.5 font-semibold text-white transition-colors disabled:opacity-60"
            >
                <Sparkles className="size-5" />
                {t('product.requestButton')}
            </button>
        </form>
    );
}

/** WhatsApp / X / Facebook share + copy-link, brand-styled. */
function ShareRow({ url, name }: { url: string; name: string }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            /* clipboard unavailable — ignore */
        }
    };

    const links = [
        { key: 'whatsapp', href: `https://wa.me/?text=${encodeURIComponent(`${name} ${url}`)}`, icon: <WhatsAppIcon /> },
        { key: 'x', href: `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(name)}`, icon: <XIcon /> },
        { key: 'facebook', href: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`, icon: <FacebookIcon /> },
    ];
    const btn = 'flex size-9 items-center justify-center rounded-full bg-brand-cream text-brand-teal transition-colors hover:bg-brand-gold/20';

    return (
        <div className="border-brand-gold/15 mt-6 flex items-center gap-3 border-t pt-5">
            <span className="text-brand-teal/70 text-sm font-medium">{t('product.share')}</span>
            <div className="flex items-center gap-2">
                {links.map((l) => (
                    <a key={l.key} href={l.href} target="_blank" rel="noopener noreferrer" aria-label={l.key} className={btn}>
                        {l.icon}
                    </a>
                ))}
                <button type="button" onClick={copy} aria-label={t('product.copyLink')} className={btn}>
                    {copied ? <Check className="text-brand-teal size-4" /> : <Link2 className="size-4" />}
                </button>
            </div>
            {copied && <span className="text-brand-teal/60 text-xs">{t('product.linkCopied')}</span>}
        </div>
    );
}

const WhatsAppIcon = () => (
    <svg viewBox="0 0 24 24" fill="currentColor" className="size-4" aria-hidden>
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
    </svg>
);

const XIcon = () => (
    <svg viewBox="0 0 24 24" fill="currentColor" className="size-4" aria-hidden>
        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
    </svg>
);

const FacebookIcon = () => (
    <svg viewBox="0 0 24 24" fill="currentColor" className="size-4" aria-hidden>
        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
    </svg>
);

function ReviewForm({ slug }: { slug: string }) {
    const { t } = useTranslation();
    const [rating, setRating] = useState(5);
    const { data, setData, post, processing, errors, reset } = useForm({ rating: 5, title: '', body: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/products/${slug}/reviews`, { preserveScroll: true, onSuccess: () => reset('title', 'body') });
    };

    return (
        <form onSubmit={submit} className="border-brand-gold/15 mb-6 rounded-xl border bg-white p-4">
            <p className="text-brand-teal mb-2 text-sm font-semibold">{t('product.addYourReview')}</p>
            <div className="mb-3 flex gap-1" dir="ltr">
                {[1, 2, 3, 4, 5].map((n) => (
                    <button
                        key={n}
                        type="button"
                        onClick={() => {
                            setRating(n);
                            setData('rating', n);
                        }}
                        aria-label={`${n} / 5`}
                    >
                        <Star className={`size-6 ${n <= rating ? 'fill-brand-gold text-brand-gold' : 'fill-brand-gold/15 text-brand-gold/25'}`} />
                    </button>
                ))}
            </div>
            {errors.rating && <p className="text-xs text-red-500">{errors.rating}</p>}
            <input
                value={data.title}
                onChange={(e) => setData('title', e.target.value)}
                placeholder={t('product.titlePlaceholder')}
                className="border-brand-gold/30 text-brand-teal focus:border-brand-teal mb-2 w-full rounded-lg border px-3 py-2 text-sm focus:outline-none"
            />
            <textarea
                value={data.body}
                onChange={(e) => setData('body', e.target.value)}
                placeholder={t('product.bodyPlaceholder')}
                rows={3}
                className="border-brand-gold/30 text-brand-teal focus:border-brand-teal w-full rounded-lg border px-3 py-2 text-sm focus:outline-none"
            />
            <button
                type="submit"
                disabled={processing}
                className="bg-brand-teal hover:bg-brand-teal/90 mt-3 rounded-full px-5 py-2 text-sm font-semibold text-white disabled:opacity-60"
            >
                {t('product.publish')}
            </button>
        </form>
    );
}
