import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import Select from '@/components/admin/select';
import { useAdminT } from '@/i18n/use-admin-t';

interface ReviewData {
    id: number;
    author_name: string;
    body: string;
    rating: number;
    language: string | null;
    is_active: boolean;
}

export default function ClientReviewForm({ review }: { review: ReviewData | null }) {
    const { t } = useAdminT();
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
        if (review && confirm(t('admin.reviews.form.deleteConfirm'))) {
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
        <AdminLayout title={review ? t('admin.reviews.form.editTitle', { name: review.author_name }) : t('admin.reviews.form.newTitle')}>
            <Head title={review ? t('admin.reviews.form.editHead') : t('admin.reviews.form.newTitle')} />

            <div className="mb-4">
                <Link href="/admin/client-reviews" className="inline-flex items-center gap-1 text-sm text-neutral-500 underline">
                    <ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {t('admin.reviews.title')}
                </Link>
            </div>

            <form onSubmit={submit} className="max-w-2xl space-y-4 rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                {field(t('admin.reviews.form.authorName'), (
                    <input dir="auto" value={data.author_name} onChange={(e) => setData('author_name', e.target.value)} className={inputCls} />
                ), errors.author_name)}

                {field(t('admin.reviews.form.reviewText'), (
                    <textarea dir="auto" rows={5} value={data.body} onChange={(e) => setData('body', e.target.value)} className={inputCls} />
                ), errors.body)}

                <div className="grid gap-4 sm:grid-cols-2">
                    {field(t('admin.reviews.form.rating'), (
                        <Select
                            value={String(data.rating)}
                            onChange={(v) => setData('rating', Number(v))}
                            options={[5, 4, 3, 2, 1].map((n) => ({ value: String(n), label: `${n} ★` }))}
                            className="mt-1 w-full"
                        />
                    ), errors.rating)}
                    {field(t('admin.reviews.form.language'), (
                        <Select
                            value={data.language}
                            onChange={(v) => setData('language', v)}
                            options={[
                                { value: '', label: t('admin.reviews.form.languageUnset') },
                                { value: 'ar', label: t('admin.reviews.form.arabic') },
                                { value: 'en', label: t('admin.reviews.form.english') },
                            ]}
                            className="mt-1 w-full"
                        />
                    ), errors.language)}
                </div>

                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                    {t('admin.reviews.form.activeLabel')}
                </label>

                <div className="flex items-center justify-between pt-2">
                    <Button type="submit" variant="primary" disabled={processing}>{t('admin.reviews.form.save')}</Button>
                    {review && (
                        <Button type="button" variant="danger" icon={Trash2} onClick={destroy}>{t('admin.common.delete')}</Button>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
