import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocalized } from '@/lib/localize';
import StoreLayout from '@/layouts/store-layout';

interface Product {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    description_ar: string | null;
    description_en: string | null;
    price: number;
    sale_price: number | null;
    effective_price: number;
    on_sale: boolean;
    in_stock: boolean;
    category: { name_ar: string; name_en: string | null; slug: string } | null;
    images: string[];
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

function Stars({ value, className = '' }: { value: number; className?: string }) {
    return (
        <span className={`text-amber-500 ${className}`} dir="ltr" aria-label={`${value} / 5`}>
            {'★'.repeat(Math.round(value))}
            <span className="text-gray-300">{'★'.repeat(5 - Math.round(value))}</span>
        </span>
    );
}

export default function ShopProduct({
    product,
    reviews,
    wishlisted,
    authed,
}: {
    product: Product;
    reviews: Reviews;
    wishlisted: boolean;
    authed: boolean;
}) {
    const { t } = useTranslation();
    const localized = useLocalized();
    const currency = t('common.currency');
    const name = localized(product, 'name');
    const description = localized(product, 'description');

    return (
        <StoreLayout>
            <Head title={name} />

            <nav className="mb-6 text-sm text-gray-500">
                <Link href="/" className="hover:underline">{t('common.home')}</Link>
                {product.category && (
                    <>
                        {' / '}
                        <Link href={`/?category=${product.category.slug}`} className="hover:underline">
                            {localized(product.category, 'name')}
                        </Link>
                    </>
                )}
            </nav>

            <div className="grid gap-8 md:grid-cols-2">
                {product.images.length > 0 ? (
                    <div className="space-y-3">
                        <img src={product.images[0]} alt={name} className="aspect-square w-full rounded-lg object-cover" />
                        {product.images.length > 1 && (
                            <div className="grid grid-cols-4 gap-2">
                                {product.images.slice(1, 5).map((url) => (
                                    <img key={url} src={url} alt="" className="aspect-square w-full rounded object-cover" />
                                ))}
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="flex aspect-square items-center justify-center rounded-lg bg-[#f1ede7] text-7xl">🌴</div>
                )}

                <div>
                    <div className="flex items-start justify-between gap-3">
                        <h1 className="text-2xl font-bold">{name}</h1>
                        {authed && (
                            <button
                                type="button"
                                onClick={() => router.post(`/wishlist/${product.slug}/toggle`, {}, { preserveScroll: true })}
                                className="shrink-0 text-2xl leading-none"
                                title={wishlisted ? t('product.wishlistRemove') : t('product.wishlistAdd')}
                            >
                                <span className={wishlisted ? 'text-red-500' : 'text-gray-300'}>♥</span>
                            </button>
                        )}
                    </div>

                    {reviews.summary.count > 0 && (
                        <div className="mt-2 flex items-center gap-2 text-sm text-gray-600">
                            <Stars value={reviews.summary.average} />
                            <span>{reviews.summary.average}</span>
                            <span className="text-gray-400">{t('product.ratingCount', { n: reviews.summary.count })}</span>
                        </div>
                    )}

                    <div className="mt-3 flex items-center gap-3">
                        {product.on_sale ? (
                            <>
                                <span className="text-2xl font-bold text-[#2f4f4f]">{product.effective_price} {currency}</span>
                                <span className="text-gray-400 line-through">{product.price} {currency}</span>
                            </>
                        ) : (
                            <span className="text-2xl font-bold text-[#2f4f4f]">{product.price} {currency}</span>
                        )}
                    </div>

                    {description && (
                        <p className="mt-4 leading-relaxed text-gray-600">{description}</p>
                    )}

                    <div className="mt-6">
                        {product.in_stock ? (
                            <button
                                type="button"
                                onClick={() =>
                                    router.post('/cart', { product_id: product.id, quantity: 1 }, { preserveScroll: true })
                                }
                                className="rounded-lg bg-[#2f4f4f] px-6 py-3 font-semibold text-white transition hover:bg-[#264141]"
                            >
                                {t('product.addToCart')}
                            </button>
                        ) : (
                            <span className="rounded-lg bg-gray-200 px-6 py-3 font-semibold text-gray-500">
                                {t('product.outOfStock')}
                            </span>
                        )}
                    </div>
                </div>
            </div>

            {/* Reviews */}
            <section className="mt-12">
                <h2 className="mb-4 text-xl font-bold">{t('product.reviewsHeading')}</h2>

                {reviews.can_review && <ReviewForm slug={product.slug} />}

                {!authed && (
                    <p className="mb-6 text-sm text-gray-500">
                        <Link href="/login/whatsapp" className="text-[#2f4f4f] underline">{t('product.signInLink')}</Link> {t('product.signInPrompt')}
                    </p>
                )}

                {reviews.items.length === 0 ? (
                    <p className="text-sm text-gray-400">{t('product.noReviews')}</p>
                ) : (
                    <ul className="space-y-4">
                        {reviews.items.map((r) => (
                            <li key={r.id} className="rounded-lg border border-gray-200 bg-white p-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Stars value={r.rating} />
                                        <span className="text-sm font-semibold">{r.author}</span>
                                        {r.is_mine && <span className="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{t('product.yourReview')}</span>}
                                    </div>
                                    <span className="text-xs text-gray-400">{r.date}</span>
                                </div>
                                {r.title && <p className="mt-2 font-semibold">{r.title}</p>}
                                {r.body && <p className="mt-1 text-sm leading-relaxed text-gray-600">{r.body}</p>}

                                {authed && !r.is_mine && (
                                    <button
                                        type="button"
                                        onClick={() => router.post(`/reviews/${r.id}/helpful`, {}, { preserveScroll: true })}
                                        className={`mt-3 text-xs ${r.voted ? 'font-semibold text-[#2f4f4f]' : 'text-gray-500'}`}
                                    >
                                        {t('product.helpful', { n: r.helpful_count })}
                                    </button>
                                )}
                                {(!authed || r.is_mine) && r.helpful_count > 0 && (
                                    <span className="mt-3 block text-xs text-gray-400">{t('product.helpful', { n: r.helpful_count })}</span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </StoreLayout>
    );
}

function ReviewForm({ slug }: { slug: string }) {
    const { t } = useTranslation();
    const [rating, setRating] = useState(5);
    const { data, setData, post, processing, errors, reset } = useForm({ rating: 5, title: '', body: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/products/${slug}/reviews`, {
            preserveScroll: true,
            onSuccess: () => reset('title', 'body'),
        });
    };

    return (
        <form onSubmit={submit} className="mb-6 rounded-lg border border-gray-200 bg-white p-4">
            <p className="mb-2 text-sm font-semibold">{t('product.addYourReview')}</p>
            <div className="mb-3 flex gap-1" dir="ltr">
                {[1, 2, 3, 4, 5].map((n) => (
                    <button
                        key={n}
                        type="button"
                        onClick={() => {
                            setRating(n);
                            setData('rating', n);
                        }}
                        className={`text-2xl ${n <= rating ? 'text-amber-500' : 'text-gray-300'}`}
                    >
                        ★
                    </button>
                ))}
            </div>
            {errors.rating && <p className="text-xs text-red-500">{errors.rating}</p>}
            <input
                value={data.title}
                onChange={(e) => setData('title', e.target.value)}
                placeholder={t('product.titlePlaceholder')}
                className="mb-2 w-full rounded border border-gray-300 px-3 py-2 text-sm"
            />
            <textarea
                value={data.body}
                onChange={(e) => setData('body', e.target.value)}
                placeholder={t('product.bodyPlaceholder')}
                rows={3}
                className="w-full rounded border border-gray-300 px-3 py-2 text-sm"
            />
            <button
                type="submit"
                disabled={processing}
                className="mt-3 rounded-lg bg-[#2f4f4f] px-5 py-2 text-sm font-semibold text-white hover:bg-[#264141] disabled:opacity-60"
            >
                {t('product.publish')}
            </button>
        </form>
    );
}
