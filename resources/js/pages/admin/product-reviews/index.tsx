import { Head, router } from '@inertiajs/react';
import { BadgeCheck, ExternalLink, Eye, EyeOff, Star, ThumbsUp, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import Button from '@/components/admin/button';
import ConfirmDialog from '@/components/admin/confirm-dialog';
import Pagination from '@/components/admin/pagination';
import Select from '@/components/admin/select';
import StatusPill from '@/components/status-pill';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';

/**
 * Moderation for customer-written product reviews.
 *
 * 🔑 A takedown queue, not an approval inbox: reviews publish the moment a verified
 * buyer writes one (see ProductReviewController), so the job here is spotting the
 * rare bad one. That is exactly why the list AUTO-REFRESHES — nobody sits on this
 * page waiting, they arrive after a complaint, and a stale list would show the
 * review as still live after a colleague had already pulled it.
 */

const POLL_MS = 45_000;

interface ReviewRow {
    id: number;
    product: { name_ar: string; name_en: string | null; slug: string } | null;
    customer: string | null;
    rating: number;
    title: string | null;
    body: string | null;
    verified: boolean;
    helpful_count: number;
    approved: boolean;
    created_at: string | null;
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Stats {
    total: number;
    published: number;
    hidden: number;
    average: number | null;
}

/** Five stars with the filled ones lit. Read-only — the rating is the customer's. */
function Stars({ rating }: { rating: number }) {
    return (
        <span className="inline-flex items-center gap-0.5" aria-hidden="true">
            {[1, 2, 3, 4, 5].map((n) => (
                <Star key={n} className={n <= rating ? 'text-brand-gold h-3.5 w-3.5 fill-current' : 'h-3.5 w-3.5 text-neutral-600'} />
            ))}
        </span>
    );
}

export default function ProductReviewsIndex({
    reviews,
    stats,
    filters,
}: {
    reviews: Paginator<ReviewRow>;
    stats: Stats;
    filters: { status: string | null; rating: number | null; q: string | null; per_page: number };
}) {
    const { t, i18n } = useAdminT();
    const loc = (ar: string | null, en: string | null) => (i18n.language === 'en' && en ? en : (ar ?? '—'));

    const [confirming, setConfirming] = useState<ReviewRow | null>(null);
    const [search, setSearch] = useState(filters.q ?? '');
    const [refreshedAt, setRefreshedAt] = useState<Date | null>(null);

    // Keep the latest value readable inside the poll effect without making it a
    // dependency, which would tear down and restart the poll on every keystroke.
    const paused = useRef(false);
    paused.current = confirming !== null;

    /**
     * Live refresh, same mechanism as the notification bell: an Inertia PARTIAL
     * reload, so the server recomputes only these two props and the current filters,
     * page and scroll position survive for free.
     *
     * ⚠️ Paused while a delete confirmation is open. The list is newest-first, so an
     * arriving review pushes every row down — refreshing underneath someone who is
     * reading a row to decide whether to delete it is how the wrong review gets
     * deleted. Nothing is missed: the next tick after closing the dialog catches up.
     */
    useEffect(() => {
        const { stop } = router.poll(
            POLL_MS,
            {
                only: ['reviews', 'stats'],
                // Returning false from onBefore cancels the visit; onStart would be
                // too late, the request is already away by then. These are REQUEST
                // options (2nd arg) — the 3rd arg is PollOptions and takes only
                // keepAlive / autoStart / mode.
                onBefore: () => !paused.current,
                onFinish: () => setRefreshedAt(new Date()),
            },
            { keepAlive: false },
        );

        return stop;
    }, []);

    const apply = (patch: Record<string, string | number | undefined>) =>
        router.get(
            '/admin/product-reviews',
            {
                status: filters.status || undefined,
                rating: filters.rating || undefined,
                q: filters.q || undefined,
                per_page: filters.per_page || undefined,
                ...patch,
            },
            { preserveState: true, preserveScroll: true },
        );

    const card = 'rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900';

    return (
        <AdminLayout title={t('admin.productReviews.title')}>
            <Head title={t('admin.productReviews.title')} />

            <p className="mb-4 text-sm text-neutral-400">{t('admin.productReviews.intro')}</p>

            <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div className={card}>
                    <p className="text-xs text-neutral-500">{t('admin.productReviews.stats.total')}</p>
                    <p className="text-2xl font-bold">{stats.total}</p>
                </div>
                <div className={card}>
                    <p className="text-xs text-neutral-500">{t('admin.productReviews.stats.published')}</p>
                    <p className="text-2xl font-bold text-green-500">{stats.published}</p>
                </div>
                <div className={card}>
                    <p className="text-xs text-neutral-500">{t('admin.productReviews.stats.hidden')}</p>
                    <p className={`text-2xl font-bold ${stats.hidden > 0 ? 'text-amber-500' : ''}`}>{stats.hidden}</p>
                </div>
                <div className={card}>
                    {/* Published only, so it matches the figure the storefront shows. */}
                    <p className="text-xs text-neutral-500">{t('admin.productReviews.stats.average')}</p>
                    <p className="text-brand-gold text-2xl font-bold">{stats.average ?? '—'}</p>
                </div>
            </div>

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply({ q: search || undefined });
                    }}
                    className="flex-1"
                >
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('admin.productReviews.searchPlaceholder')}
                        aria-label={t('admin.productReviews.searchPlaceholder')}
                        className="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                    />
                </form>
                <Select
                    value={filters.status ?? ''}
                    onChange={(v) => apply({ status: v || undefined })}
                    options={[
                        { value: '', label: t('admin.productReviews.filterAll') },
                        { value: 'published', label: t('admin.productReviews.filterPublished') },
                        { value: 'hidden', label: t('admin.productReviews.filterHidden') },
                    ]}
                    className="w-full sm:w-44"
                />
                <Select
                    value={filters.rating ? String(filters.rating) : ''}
                    onChange={(v) => apply({ rating: v || undefined })}
                    options={[
                        { value: '', label: t('admin.productReviews.filterAnyRating') },
                        ...[5, 4, 3, 2, 1].map((n) => ({ value: String(n), label: t('admin.productReviews.nStars', { n }) })),
                    ]}
                    className="w-full sm:w-40"
                />
            </div>

            <div className="space-y-3">
                {reviews.data.length === 0 && <div className={`${card} py-10 text-center text-neutral-400`}>{t('admin.productReviews.empty')}</div>}

                {reviews.data.map((r) => (
                    <article key={r.id} className={`${card} ${r.approved ? '' : 'border-amber-500/40 bg-amber-500/5 dark:bg-amber-500/5'}`}>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Stars rating={r.rating} />
                                    <span className="sr-only">{t('admin.productReviews.nStars', { n: r.rating })}</span>
                                    {r.approved ? (
                                        <StatusPill tone="active" icon={Eye}>
                                            {t('admin.productReviews.published')}
                                        </StatusPill>
                                    ) : (
                                        // A hidden review is a takedown staff chose, not an
                                        // outstanding job, so it stays quiet.
                                        <StatusPill tone="idle" icon={EyeOff}>
                                            {t('admin.productReviews.hidden')}
                                        </StatusPill>
                                    )}
                                    {r.verified && (
                                        <StatusPill tone="done" icon={BadgeCheck}>
                                            {t('admin.productReviews.verified')}
                                        </StatusPill>
                                    )}
                                    {r.helpful_count > 0 && (
                                        <span className="inline-flex items-center gap-1 text-xs text-neutral-500">
                                            <ThumbsUp className="h-3.5 w-3.5" /> {r.helpful_count}
                                        </span>
                                    )}
                                </div>

                                <p className="mt-2 text-sm text-neutral-400">
                                    {r.product ? (
                                        <a
                                            href={`/products/${r.product.slug}#reviews`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            dir="auto"
                                            className="text-brand-gold inline-flex items-center gap-1 hover:underline"
                                        >
                                            {loc(r.product.name_ar, r.product.name_en)}
                                            <ExternalLink className="h-3.5 w-3.5" />
                                        </a>
                                    ) : (
                                        <span className="text-neutral-500">{t('admin.productReviews.productGone')}</span>
                                    )}
                                    <span className="mx-2 text-neutral-600">·</span>
                                    <span dir="auto">{r.customer ?? t('admin.productReviews.anonymous')}</span>
                                    <span className="mx-2 text-neutral-600">·</span>
                                    <span className="whitespace-nowrap">{r.created_at ?? '—'}</span>
                                </p>
                            </div>

                            <div className="flex shrink-0 items-center gap-2">
                                {/* Not a StatusToggle: that renders a pill, and the status
                                    pill above already states the state. This is the verb. */}
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    icon={r.approved ? EyeOff : Eye}
                                    onClick={() =>
                                        router.patch(`/admin/product-reviews/${r.id}/toggle`, {}, { preserveScroll: true, preserveState: true })
                                    }
                                >
                                    {r.approved ? t('admin.productReviews.hide') : t('admin.productReviews.show')}
                                </Button>
                                <Button size="sm" variant="danger" icon={Trash2} onClick={() => setConfirming(r)}>
                                    {t('admin.common.delete')}
                                </Button>
                            </div>
                        </div>

                        {(r.title || r.body) && (
                            <div className="mt-3 border-t border-neutral-100 pt-3 dark:border-neutral-800">
                                {/* dir="auto" per review: the admin is LTR-pinned but the
                                    copy is whatever language the customer wrote in. */}
                                {r.title && (
                                    <p className="font-medium" dir="auto">
                                        {r.title}
                                    </p>
                                )}
                                {r.body && (
                                    <p data-testid="review-body" className="mt-1 text-sm whitespace-pre-wrap text-neutral-300" dir="auto">
                                        {r.body}
                                    </p>
                                )}
                            </div>
                        )}
                    </article>
                ))}
            </div>

            <Pagination paginator={reviews} perPage={filters.per_page} />

            <p className="mt-4 flex items-center gap-2 text-xs text-neutral-500">
                <span className="relative flex h-2 w-2">
                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-60" />
                    <span className="relative inline-flex h-2 w-2 rounded-full bg-green-500" />
                </span>
                {t('admin.productReviews.live')}
                {refreshedAt && <span className="text-neutral-600">· {refreshedAt.toLocaleTimeString()}</span>}
            </p>

            <ConfirmDialog
                open={confirming !== null}
                title={t('admin.productReviews.deleteTitle')}
                message={t('admin.productReviews.deleteBody')}
                confirmLabel={t('admin.common.delete')}
                confirmVariant="danger"
                icon={Trash2}
                onClose={() => setConfirming(null)}
                onConfirm={() => {
                    const id = confirming?.id;
                    setConfirming(null);
                    if (id) router.delete(`/admin/product-reviews/${id}`, { preserveScroll: true });
                }}
            />
        </AdminLayout>
    );
}
