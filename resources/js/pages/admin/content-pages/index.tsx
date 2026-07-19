import { Head, useForm } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import UndoButton, { type UndoMeta } from '@/components/admin/undo-button';
import { useHighlightFields } from '@/hooks/use-highlight-fields';
import { useAdminT } from '@/i18n/use-admin-t';

interface PageItem {
    id: number;
    slug: string;
    title_ar: string;
    title_en: string | null;
    body_ar: string;
    body_en: string | null;
    is_published: boolean;
    updated_at: string | null;
    updated_by_name: string | null;
}

const INPUT =
    'mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 shadow-sm transition-colors focus:border-brand-gold focus:outline-none focus:ring-1 focus:ring-brand-gold dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100';

// Right pane: the editor for the selected page. Keyed by page id in the parent
// so it remounts (and re-seeds the form) whenever the selection changes.
function PageEditor({ page }: { page: PageItem }) {
    const { t } = useAdminT();
    useHighlightFields();
    const { data, setData, put, processing, errors, isDirty } = useForm({
        slug: page.slug,
        title_ar: page.title_ar,
        title_en: page.title_en ?? '',
        body_ar: page.body_ar,
        body_en: page.body_en ?? '',
        is_published: page.is_published,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/content-pages/${page.id}`, { preserveScroll: true, preserveState: true });
    };

    const field = (name: string, label: string, el: ReactNode, error?: string) => (
        <label className="block" id={`field-${name}`}>
            <span className="text-sm font-medium text-neutral-600 dark:text-neutral-300">{label}</span>
            {el}
            {error && <span className="mt-1 block text-xs text-red-500">{error}</span>}
        </label>
    );

    return (
        <form onSubmit={submit} className="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 sm:p-6">
            <div className="mb-5 flex items-start justify-between gap-3 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                <div className="min-w-0">
                    <h2 className="flex items-center gap-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        <FileText className="h-4 w-4 shrink-0 text-brand-gold" />
                        <span className="truncate">{data.title_en || data.title_ar}</span>
                    </h2>
                    <p className="mt-1 text-xs text-neutral-400">
                        {page.updated_by_name
                            ? t('admin.contentPages.updatedBy', { by: page.updated_by_name, at: page.updated_at ?? '' })
                            : t('admin.contentPages.updatedAt', { at: page.updated_at ?? '' })}
                    </p>
                </div>
                <span
                    className={`shrink-0 rounded-full px-2 py-0.5 text-xs ${
                        page.is_published
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                    }`}
                >
                    {page.is_published ? t('admin.contentPages.publishedBadge') : t('admin.contentPages.draftBadge')}
                </span>
            </div>

            <div className="space-y-4">
                {field('slug', t('admin.contentPages.form.slug'), (
                    <input value={data.slug} onChange={(e) => setData('slug', e.target.value)} className={`${INPUT} font-mono`} />
                ), errors.slug)}

                <div className="grid gap-4 sm:grid-cols-2">
                    {field('title_ar', t('admin.contentPages.form.titleAr'), (
                        <input dir="rtl" value={data.title_ar} onChange={(e) => setData('title_ar', e.target.value)} className={INPUT} />
                    ), errors.title_ar)}
                    {field('title_en', t('admin.contentPages.form.titleEn'), (
                        <input value={data.title_en} onChange={(e) => setData('title_en', e.target.value)} className={INPUT} />
                    ), errors.title_en)}
                </div>

                {field('body_ar', t('admin.contentPages.form.bodyAr'), (
                    <textarea dir="rtl" rows={10} value={data.body_ar} onChange={(e) => setData('body_ar', e.target.value)} className={INPUT} />
                ), errors.body_ar)}
                {field('body_en', t('admin.contentPages.form.bodyEn'), (
                    <textarea rows={10} value={data.body_en} onChange={(e) => setData('body_en', e.target.value)} className={INPUT} />
                ), errors.body_en)}

                <label className="flex items-center gap-2 text-sm" id="field-is_published">
                    <input
                        type="checkbox"
                        checked={data.is_published}
                        onChange={(e) => setData('is_published', e.target.checked)}
                        className="h-4 w-4 accent-brand-gold"
                    />
                    {t('admin.contentPages.form.publishedLabel')}
                </label>

                <Button type="submit" variant="primary" disabled={processing || !isDirty}>
                    {t('admin.contentPages.form.save')}
                </Button>
            </div>
        </form>
    );
}

export default function ContentPagesIndex({ pages, undoMeta = null }: { pages: PageItem[]; undoMeta?: UndoMeta | null }) {
    const { t } = useAdminT();
    // Default to the About page, falling back to the first available page.
    const [selectedId, setSelectedId] = useState<number | null>(
        () => pages.find((p) => p.slug === 'about')?.id ?? pages[0]?.id ?? null,
    );
    const selected = pages.find((p) => p.id === selectedId) ?? null;

    return (
        <AdminLayout title={t('admin.contentPages.title')}>
            <Head title={t('admin.contentPages.title')} />

            {undoMeta && (
                <div className="mb-4">
                    <UndoButton section="content_pages" undoMeta={undoMeta} />
                </div>
            )}

            <div className="lg:flex lg:gap-6">
                {/* Left: page picker */}
                <nav className="mb-4 lg:mb-0 lg:w-64 lg:shrink-0">
                    <div className="rounded-xl border border-neutral-200 bg-white p-2 dark:border-neutral-800 dark:bg-neutral-900">
                        {pages.length === 0 && <p className="px-3 py-2 text-sm text-neutral-400">{t('admin.contentPages.empty')}</p>}
                        {pages.map((p) => {
                            const on = p.id === selectedId;
                            return (
                                <button
                                    key={p.id}
                                    type="button"
                                    onClick={() => setSelectedId(p.id)}
                                    aria-current={on ? 'page' : undefined}
                                    className={`flex w-full items-start gap-2.5 rounded-lg px-3 py-2 text-start text-sm transition-colors ${
                                        on
                                            ? 'bg-brand-teal/20 text-brand-gold'
                                            : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'
                                    }`}
                                >
                                    <FileText className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-medium">{p.title_en || p.title_ar}</span>
                                        <span className="block truncate font-mono text-xs text-neutral-400">{p.slug}</span>
                                    </span>
                                    {!p.is_published && (
                                        <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" title={t('admin.contentPages.draftBadge')} />
                                    )}
                                </button>
                            );
                        })}
                    </div>
                </nav>

                {/* Right: editor */}
                <div className="min-w-0 flex-1">
                    {selected ? (
                        // Key on updated_at too: after a save the timestamp changes, so the
                        // editor remounts with fresh data and its dirty baseline resets.
                        <PageEditor key={`${selected.id}:${selected.updated_at ?? ''}`} page={selected} />
                    ) : (
                        <p className="text-neutral-400">{t('admin.contentPages.empty')}</p>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
