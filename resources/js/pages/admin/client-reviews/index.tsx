import { Head, Link } from '@inertiajs/react';
import { Plus, Upload } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import { useAdminT } from '@/i18n/use-admin-t';

interface ReviewRow {
    id: number;
    author_name: string;
    body: string;
    rating: number;
    language: string | null;
    source: string | null;
    is_active: boolean;
    updated_at: string | null;
}

export default function ClientReviewsIndex({ reviews }: { reviews: ReviewRow[] }) {
    const { t } = useAdminT();
    const activeCount = reviews.filter((r) => r.is_active).length;

    return (
        <AdminLayout title={t('admin.reviews.title')}>
            <Head title={t('admin.reviews.title')} />

            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-neutral-500">
                    {t('admin.reviews.summary', { active: activeCount, total: reviews.length })}
                </p>
                <div className="flex gap-2">
                    <Button href="/admin/client-reviews/import" variant="secondary" icon={Upload}>{t('admin.reviews.bulkImport')}</Button>
                    <Button href="/admin/client-reviews/create" variant="primary" icon={Plus}>{t('admin.reviews.newReview')}</Button>
                </div>
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-sm">
                    <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">{t('admin.reviews.cols.author')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.reviews.cols.review')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.reviews.cols.rating')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.reviews.cols.source')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.reviews.cols.inPool')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.reviews.cols.updated')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {reviews.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-8 text-center text-neutral-400">{t('admin.reviews.empty')}</td></tr>
                        )}
                        {reviews.map((r) => (
                            <tr key={r.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td className="px-4 py-3 align-top">
                                    <Link href={`/admin/client-reviews/${r.id}/edit`} className="font-semibold text-blue-600 underline dark:text-blue-400">
                                        {r.author_name}
                                    </Link>
                                    {r.language && <span className="ms-2 text-xs uppercase text-neutral-400">{r.language}</span>}
                                </td>
                                <td className="max-w-md px-4 py-3 align-top text-neutral-600 dark:text-neutral-300">
                                    <span dir="auto" className="line-clamp-2 block">{r.body}</span>
                                </td>
                                <td className="px-4 py-3 align-top text-amber-500">{'★'.repeat(r.rating)}</td>
                                <td className="px-4 py-3 align-top text-neutral-500">{r.source ?? '—'}</td>
                                <td className="px-4 py-3 align-top">
                                    <span className={r.is_active ? 'font-semibold text-green-600' : 'text-neutral-400'}>
                                        {r.is_active ? t('admin.common.yes') : t('admin.common.no')}
                                    </span>
                                </td>
                                <td className="px-4 py-3 align-top text-neutral-500">{r.updated_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
