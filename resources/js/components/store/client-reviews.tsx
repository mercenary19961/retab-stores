import { useTranslation } from 'react-i18next';

interface Review {
    id: number;
    author_name: string;
    body: string;
    rating: number;
}

/** Gold five-point star (from Star 6.svg). Grey when not filled. */
function Star({ filled }: { filled: boolean }) {
    return (
        <svg width="17" height="16" viewBox="0 0 30 29" fill="none" aria-hidden>
            <path
                d="M14.7412 0L18.2212 10.7102H29.4826L20.3719 17.3295L23.8519 28.0398L14.7412 21.4205L5.63054 28.0398L9.11051 17.3295L-0.000164986 10.7102H11.2612L14.7412 0Z"
                fill={filled ? '#F4CD21' : '#d4d4d4'}
            />
        </svg>
    );
}

function ReviewCard({ review }: { review: Review }) {
    const { t } = useTranslation();
    const initial = review.author_name.trim().charAt(0).toUpperCase();
    const filled = Math.round(review.rating);

    return (
        <div className="rounded-[2rem] bg-[#d9d9d9]/45 p-6 sm:p-7">
            <div className="flex items-center gap-3">
                <div className="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-gold text-lg font-bold text-white">
                    {initial}
                </div>
                <div className="min-w-0">
                    <p dir="auto" className="truncate font-bold text-brand-teal">
                        {review.author_name}
                    </p>
                    <p className="text-xs font-medium uppercase tracking-wide text-neutral-400">
                        {t('clientReviews.client')}
                    </p>
                </div>
            </div>

            <p dir="auto" className="mt-4 text-sm leading-relaxed text-neutral-600">
                {review.body}
            </p>

            <div className="mt-4 flex items-center gap-1">
                {[0, 1, 2, 3, 4].map((i) => (
                    <Star key={i} filled={i < filled} />
                ))}
                <span className="ms-2 text-sm font-semibold text-neutral-500">{review.rating.toFixed(1)}</span>
            </div>
        </div>
    );
}

/**
 * "آراء العملاء" homepage section — a feature card (brand photo + intro copy) on
 * the start side, plus a 2×2 grid of curated client reviews. The reviews are a
 * random draw of the active pool (rotates each request; see ShopController), and
 * a faint band (Asset 5 1) runs along the bottom.
 */
export default function ClientReviews({ reviews }: { reviews: Review[] }) {
    const { t } = useTranslation();

    if (reviews.length === 0) return null;

    return (
        <section className="relative w-full overflow-hidden bg-white py-12 sm:py-16">
            {/* Faint geometric band along the bottom, behind the cards. */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 bottom-0 h-14 bg-repeat-x"
                style={{
                    backgroundImage: "url('/images/reviews/pattern.png')",
                    backgroundPosition: 'center bottom',
                    backgroundSize: 'auto 100%',
                }}
            />

            <div className="relative z-10 mx-auto max-w-[1600px] px-6 lg:px-12">
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                    {/* Feature card — start side (right in RTL); spans both rows on lg. */}
                    <div
                        className="relative flex min-h-[22rem] flex-col justify-center overflow-hidden rounded-[2rem] bg-cover bg-center p-8 text-center md:col-span-2 lg:col-span-1 lg:row-span-2 lg:min-h-full"
                        style={{ backgroundImage: "url('/images/reviews/feature.png')" }}
                    >
                        <div className="absolute inset-0 bg-black/45" />
                        <div className="relative">
                            <h2 className="font-heading font-black text-brand-gold text-[clamp(1.75rem,3vw,2.5rem)]">
                                {t('clientReviews.title')}
                            </h2>
                            <p className="mt-5 text-justify text-sm leading-loose text-white/90">
                                {t('clientReviews.intro')}
                            </p>
                        </div>
                    </div>

                    {reviews.map((r) => (
                        <ReviewCard key={r.id} review={r} />
                    ))}
                </div>
            </div>
        </section>
    );
}
