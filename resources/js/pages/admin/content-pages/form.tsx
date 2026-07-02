import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';

interface PageData {
    id: number;
    slug: string;
    title_ar: string;
    title_en: string | null;
    body_ar: string;
    body_en: string | null;
    is_published: boolean;
}

export default function ContentPageForm({ page }: { page: PageData | null }) {
    const { data, setData, post, put, processing, errors } = useForm({
        slug: page?.slug ?? '',
        title_ar: page?.title_ar ?? '',
        title_en: page?.title_en ?? '',
        body_ar: page?.body_ar ?? '',
        body_en: page?.body_en ?? '',
        is_published: page?.is_published ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (page) {
            put(`/admin/content-pages/${page.id}`, { preserveScroll: true });
        } else {
            post('/admin/content-pages', { preserveScroll: true });
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
        <AdminLayout title={page ? `Edit: ${page.slug}` : 'New page'}>
            <Head title={page ? `Edit ${page.slug}` : 'New page'} />

            <div className="mb-4">
                <Link href="/admin/content-pages" className="text-sm text-neutral-500 underline">← Content pages</Link>
            </div>

            <form onSubmit={submit} className="max-w-3xl space-y-4 rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                {field('Slug (public URL: /pages/{slug})', (
                    <input value={data.slug} onChange={(e) => setData('slug', e.target.value)} className={`${inputCls} font-mono`} />
                ), errors.slug)}

                <div className="grid gap-4 sm:grid-cols-2">
                    {field('Title (AR) *', (
                        <input dir="rtl" value={data.title_ar} onChange={(e) => setData('title_ar', e.target.value)} className={inputCls} />
                    ), errors.title_ar)}
                    {field('Title (EN)', (
                        <input value={data.title_en} onChange={(e) => setData('title_en', e.target.value)} className={inputCls} />
                    ), errors.title_en)}
                </div>

                {field('Body (AR) *', (
                    <textarea dir="rtl" rows={10} value={data.body_ar} onChange={(e) => setData('body_ar', e.target.value)} className={inputCls} />
                ), errors.body_ar)}
                {field('Body (EN)', (
                    <textarea rows={10} value={data.body_en} onChange={(e) => setData('body_en', e.target.value)} className={inputCls} />
                ), errors.body_en)}

                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={data.is_published} onChange={(e) => setData('is_published', e.target.checked)} />
                    Published (visible on the storefront)
                </label>

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-lg bg-neutral-900 px-5 py-2 text-sm font-semibold text-white hover:bg-neutral-700 disabled:opacity-60 dark:bg-white dark:text-neutral-900"
                >
                    Save page
                </button>
            </form>
        </AdminLayout>
    );
}
