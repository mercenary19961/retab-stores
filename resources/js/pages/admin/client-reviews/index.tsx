import { Head, router } from '@inertiajs/react';
import { Columns3, MoveHorizontal, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import ResizableTh from '@/components/admin/resizable-th';
import StickyScrollWrapper from '@/components/admin/sticky-scroll-wrapper';
import { useResizableColumns, type ColumnDef } from '@/hooks/use-resizable-columns';
import { useAdminT } from '@/i18n/use-admin-t';

const COLUMNS: ColumnDef[] = [
    { key: 'author', defaultWidth: 190, minWidth: 120 },
    { key: 'review', defaultWidth: 420, minWidth: 200 },
    { key: 'rating', defaultWidth: 120, minWidth: 90 },
    { key: 'source', defaultWidth: 110, minWidth: 80 },
    { key: 'inPool', defaultWidth: 110, minWidth: 80 },
    { key: 'updated', defaultWidth: 170, minWidth: 120 },
    { key: 'actions', defaultWidth: 170, minWidth: 130 },
];

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
    const rc = useResizableColumns({ tableKey: 'client_reviews', columns: COLUMNS });
    const activeCount = reviews.filter((r) => r.is_active).length;

    const destroy = (r: ReviewRow) => {
        if (!window.confirm(t('admin.reviews.form.deleteConfirm'))) return;
        router.delete(`/admin/client-reviews/${r.id}`, { preserveScroll: true });
    };

    return (
        <AdminLayout title={t('admin.reviews.title')}>
            <Head title={t('admin.reviews.title')} />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm text-neutral-400">{t('admin.reviews.summary', { active: activeCount, total: reviews.length })}</span>
                    {rc.isDefault ? (
                        <span className="hidden items-center gap-1.5 text-xs text-neutral-500 lg:inline-flex">
                            <MoveHorizontal className="h-3.5 w-3.5" /> {t('admin.common.dragToResize')}
                        </span>
                    ) : (
                        <Button size="sm" variant="ghost" icon={Columns3} onClick={rc.resetAll}>{t('admin.common.resetColumns')}</Button>
                    )}
                </div>
                <div className="flex gap-2">
                    <Button href="/admin/client-reviews/import" variant="secondary" icon={Upload}>{t('admin.reviews.bulkImport')}</Button>
                    <Button href="/admin/client-reviews/create" variant="primary" icon={Plus}>{t('admin.reviews.newReview')}</Button>
                </div>
            </div>

            <StickyScrollWrapper className="rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="min-w-full table-fixed text-sm" style={{ width: rc.tableWidth }}>
                    <thead className="border-b border-neutral-200 bg-neutral-50 text-left text-neutral-600 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-300">
                        <tr>
                            <ResizableTh colKey="author" width={rc.widths.author} resizeProps={rc.getResizeHandleProps('author')} resizing={rc.resizing === 'author'}>{t('admin.reviews.cols.author')}</ResizableTh>
                            <ResizableTh colKey="review" width={rc.widths.review} resizeProps={rc.getResizeHandleProps('review')} resizing={rc.resizing === 'review'}>{t('admin.reviews.cols.review')}</ResizableTh>
                            <ResizableTh colKey="rating" width={rc.widths.rating} resizeProps={rc.getResizeHandleProps('rating')} resizing={rc.resizing === 'rating'}>{t('admin.reviews.cols.rating')}</ResizableTh>
                            <ResizableTh colKey="source" width={rc.widths.source} resizeProps={rc.getResizeHandleProps('source')} resizing={rc.resizing === 'source'}>{t('admin.reviews.cols.source')}</ResizableTh>
                            <ResizableTh colKey="inPool" width={rc.widths.inPool} resizeProps={rc.getResizeHandleProps('inPool')} resizing={rc.resizing === 'inPool'}>{t('admin.reviews.cols.inPool')}</ResizableTh>
                            <ResizableTh colKey="updated" width={rc.widths.updated} resizeProps={rc.getResizeHandleProps('updated')} resizing={rc.resizing === 'updated'}>{t('admin.reviews.cols.updated')}</ResizableTh>
                            <ResizableTh colKey="actions" width={rc.widths.actions} resizeProps={rc.getResizeHandleProps('actions')} resizing={rc.resizing === 'actions'} className="text-end">{t('admin.common.actions')}</ResizableTh>
                        </tr>
                    </thead>
                    <tbody>
                        {reviews.length === 0 && (
                            <tr><td colSpan={7} className="px-4 py-8 text-center text-neutral-400">{t('admin.reviews.empty')}</td></tr>
                        )}
                        {reviews.map((r) => (
                            <tr key={r.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td className="px-4 py-3 align-top">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <span dir="auto" className="truncate font-medium text-neutral-800 dark:text-neutral-100">{r.author_name}</span>
                                        {r.language && <span className="shrink-0 text-xs uppercase text-neutral-400">{r.language}</span>}
                                    </div>
                                </td>
                                <td className="px-4 py-3 align-top text-neutral-600 dark:text-neutral-300">
                                    <span dir="auto" className="line-clamp-2 block">{r.body}</span>
                                </td>
                                <td className="whitespace-nowrap px-4 py-3 align-top">
                                    <span className="text-amber-500">{'★'.repeat(r.rating)}</span>
                                    <span className="text-neutral-300 dark:text-neutral-700">{'★'.repeat(5 - r.rating)}</span>
                                </td>
                                <td className="truncate px-4 py-3 align-top text-neutral-500">{r.source ?? '—'}</td>
                                <td className="px-4 py-3 align-top">
                                    {r.is_active ? (
                                        <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800 dark:bg-green-950 dark:text-green-200">{t('admin.reviews.activeBadge')}</span>
                                    ) : (
                                        <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{t('admin.reviews.hiddenBadge')}</span>
                                    )}
                                </td>
                                <td className="truncate px-4 py-3 align-top text-neutral-500">{r.updated_at ?? '—'}</td>
                                <td className="px-4 py-3 align-top">
                                    <div className="flex items-center justify-end gap-2">
                                        <Button size="sm" variant="secondary" icon={Pencil} href={`/admin/client-reviews/${r.id}/edit`}>{t('admin.common.edit')}</Button>
                                        <Button size="sm" variant="danger" icon={Trash2} onClick={() => destroy(r)}>{t('admin.common.delete')}</Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </StickyScrollWrapper>
        </AdminLayout>
    );
}
