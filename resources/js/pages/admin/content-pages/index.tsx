import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import UndoButton, { type UndoMeta } from '@/components/admin/undo-button';
import { useAdminT } from '@/i18n/use-admin-t';

interface PageRow {
    id: number;
    slug: string;
    title_ar: string;
    title_en: string | null;
    is_published: boolean;
    updated_at: string | null;
}

export default function ContentPagesIndex({ pages, undoMeta = null }: { pages: PageRow[]; undoMeta?: UndoMeta | null }) {
    const { t } = useAdminT();
    return (
        <AdminLayout title={t('admin.contentPages.title')}>
            <Head title={t('admin.contentPages.title')} />

            <div className="mb-4 flex items-center gap-3">
                <UndoButton section="content_pages" undoMeta={undoMeta} />
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-sm">
                    <thead className="border-b border-neutral-200 bg-neutral-50 text-left text-neutral-600 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-300">
                        <tr>
                            <th className="px-4 py-3 font-medium">{t('admin.contentPages.cols.slug')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.contentPages.cols.titleAr')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.contentPages.cols.titleEn')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.contentPages.cols.published')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.contentPages.cols.updated')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {pages.length === 0 && (
                            <tr><td colSpan={5} className="px-4 py-8 text-center text-neutral-400">{t('admin.contentPages.empty')}</td></tr>
                        )}
                        {pages.map((p) => (
                            <tr key={p.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td className="px-4 py-3">
                                    <Link href={`/admin/content-pages/${p.id}/edit`} className="font-mono text-blue-600 underline dark:text-blue-400">
                                        {p.slug}
                                    </Link>
                                </td>
                                <td className="px-4 py-3" dir="rtl">{p.title_ar}</td>
                                <td className="px-4 py-3">{p.title_en ?? '—'}</td>
                                <td className="px-4 py-3">{p.is_published ? t('admin.common.yes') : t('admin.common.no')}</td>
                                <td className="px-4 py-3 text-neutral-500">{p.updated_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
