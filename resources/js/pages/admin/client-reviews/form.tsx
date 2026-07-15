import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';

interface ReviewData {
    id: number;
    author_name: string;
    body: string;
    rating: number;
    language: string | null;
    is_active: boolean;
}

export default function ClientReviewForm({ review }: { review: ReviewData | null }) {
    const { data, setData, post, put, processing, errors } = useForm({
        author_name: review?.author_name ?? '',
        body: review?.body ?? '',
        rating: review?.rating ?? 5,
        language: review?.language ?? '',
        is_active: review?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (review) {
            put(`/admin/client-reviews/${review.id}`, { preserveScroll: true });
        } else {
            post('/admin/client-reviews', { preserveScroll: true });
        }
    };

    const destroy = () => {
        if (review && confirm('Delete this review?')) {
            router.delete(`/admin/client-reviews/${review.id}`);
        }
    };

    const field = (label: string, el: React.ReactNode, error?: string) => (
        <label className="block">
            <span className="text-sm text-neutral-500">{label}</span>
            {el}
            {error && <span className="block text-xs text-red-500">{error}</span>}
        </label>
    );

    const inputCls = 'mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-950';

    return (
        <AdminLayout title={review ? `Edit: ${review.author_name}` : 'New review'}>
            <Head title={review ? `Edit review` : 'New review'} />

            <div className="mb-4">
                <Link href="/admin/client-reviews" className="text-sm text-neutral-500 underline">← Client reviews</Link>
            </div>

            <form onSubmit={submit} className="max-w-2xl space-y-4 rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                {field('Author name *', (
                    <input dir="auto" value={data.author_name} onChange={(e) => setData('author_name', e.target.value)} className={inputCls} />
                ), errors.author_name)}

                {field('Review text *', (
                    <textarea dir="auto" rows={5} value={data.body} onChange={(e) => setData('body', e.target.value)} className={inputCls} />
                ), errors.body)}

                <div className="grid gap-4 sm:grid-cols-2">
                    {field('Rating *', (
                        <select value={data.rating} onChange={(e) => setData('rating', Number(e.target.value))} className={inputCls}>
                            {[5, 4, 3, 2, 1].map((n) => (
                                <option key={n} value={n}>{n} ★</option>
                            ))}
                        </select>
                    ), errors.rating)}
                    {field('Language (as written)', (
                        <select value={data.language} onChange={(e) => setData('language', e.target.value)} className={inputCls}>
                            <option value="">— unset —</option>
                            <option value="ar">Arabic</option>
                            <option value="en">English</option>
                        </select>
                    ), errors.language)}
                </div>

                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                    Active — include in the homepage rotation pool
                </label>

                <div className="flex items-center justify-between pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-lg bg-neutral-900 px-5 py-2 text-sm font-semibold text-white hover:bg-neutral-700 disabled:opacity-60 dark:bg-white dark:text-neutral-900"
                    >
                        Save review
                    </button>
                    {review && (
                        <button type="button" onClick={destroy} className="text-sm font-medium text-red-600 hover:underline">
                            Delete
                        </button>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
