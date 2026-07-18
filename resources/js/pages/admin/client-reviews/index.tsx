import { Head, router, useForm } from '@inertiajs/react';
import { Columns3, MoveHorizontal, Pencil, Plus, Upload } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import ConfirmDeleteButton from '@/components/admin/confirm-delete-button';
import Modal from '@/components/admin/modal';
import ResizableTh from '@/components/admin/resizable-th';
import Select from '@/components/admin/select';
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

const INPUT =
    'mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 focus:border-brand-gold focus:outline-none focus:ring-1 focus:ring-brand-gold dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100';

// Modal body: create (review = null) or edit an existing review in place.
function ReviewForm({ review, onClose }: { review: ReviewRow | null; onClose: () => void }) {
    const { t } = useAdminT();
    const { data, setData, post, put, processing, errors, isDirty } = useForm({
        author_name: review?.author_name ?? '',
        body: review?.body ?? '',
        rating: review?.rating ?? 5,
        language: review?.language ?? '',
        is_active: review?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, preserveState: true, onSuccess: () => onClose() };
        if (review) put(`/admin/client-reviews/${review.id}`, opts);
        else post('/admin/client-reviews', opts);
    };

    const err = (msg?: string) => msg && <span className="mt-1 block text-xs text-red-500">{msg}</span>;

    return (
        <form onSubmit={submit} className="space-y-4">
            <label className="block">
                <span className="text-sm font-medium text-neutral-600 dark:text-neutral-300">{t('admin.reviews.form.authorName')}</span>
                <input dir="auto" autoFocus value={data.author_name} onChange={(e) => setData('author_name', e.target.value)} className={INPUT} />
                {err(errors.author_name)}
            </label>

            <label className="block">
                <span className="text-sm font-medium text-neutral-600 dark:text-neutral-300">{t('admin.reviews.form.reviewText')}</span>
                <textarea dir="auto" rows={5} value={data.body} onChange={(e) => setData('body', e.target.value)} className={INPUT} />
                {err(errors.body)}
            </label>

            <div className="grid gap-4 sm:grid-cols-2">
                <label className="block">
                    <span className="text-sm font-medium text-neutral-600 dark:text-neutral-300">{t('admin.reviews.form.rating')}</span>
                    <Select
                        value={String(data.rating)}
                        onChange={(v) => setData('rating', Number(v))}
                        options={[5, 4, 3, 2, 1].map((n) => ({ value: String(n), label: `${n} ★` }))}
                        className="mt-1 w-full"
                    />
                    {err(errors.rating)}
                </label>
                <label className="block">
                    <span className="text-sm font-medium text-neutral-600 dark:text-neutral-300">{t('admin.reviews.form.language')}</span>
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
                    {err(errors.language)}
                </label>
            </div>

            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="h-4 w-4 accent-brand-gold" />
                {t('admin.reviews.form.activeLabel')}
            </label>

            <div className="flex justify-end gap-2 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                <Button type="button" variant="secondary" onClick={onClose}>{t('admin.common.cancel')}</Button>
                <Button type="submit" variant="primary" disabled={processing || !isDirty}>{t('admin.reviews.form.save')}</Button>
            </div>
        </form>
    );
}

export default function ClientReviewsIndex({ reviews }: { reviews: ReviewRow[] }) {
    const { t } = useAdminT();
    const rc = useResizableColumns({ tableKey: 'client_reviews', columns: COLUMNS });
    const activeCount = reviews.filter((r) => r.is_active).length;

    // null = closed, 'new' = create, ReviewRow = edit that review — all in a modal.
    const [editing, setEditing] = useState<ReviewRow | 'new' | null>(null);

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
                    <Button variant="primary" icon={Plus} onClick={() => setEditing('new')}>{t('admin.reviews.newReview')}</Button>
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
                                        <Button size="sm" variant="secondary" icon={Pencil} onClick={() => setEditing(r)}>{t('admin.common.edit')}</Button>
                                        <ConfirmDeleteButton
                                            itemName={r.author_name}
                                            onConfirm={() => router.delete(`/admin/client-reviews/${r.id}`, { preserveScroll: true })}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </StickyScrollWrapper>

            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                title={editing && editing !== 'new' ? t('admin.reviews.form.editTitle', { name: editing.author_name }) : t('admin.reviews.form.newTitle')}
            >
                {editing !== null && (
                    <ReviewForm key={editing === 'new' ? 'new' : editing.id} review={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
                )}
            </Modal>
        </AdminLayout>
    );
}
