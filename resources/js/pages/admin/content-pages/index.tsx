import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';

interface PageRow {
    id: number;
    slug: string;
    title_ar: string;
    title_en: string | null;
    is_published: boolean;
    updated_at: string | null;
}

export default function ContentPagesIndex({ pages }: { pages: PageRow[] }) {
    return (
        <AdminLayout title="Content Pages">
            <Head title="Content Pages" />

            <div className="mb-4 flex justify-end">
                <Link
                    href="/admin/content-pages/create"
                    className="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-700 dark:bg-white dark:text-neutral-900"
                >
                    New page
                </Link>
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-sm">
                    <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Slug</th>
                            <th className="px-4 py-3 font-medium">Title (AR)</th>
                            <th className="px-4 py-3 font-medium">Title (EN)</th>
                            <th className="px-4 py-3 font-medium">Published</th>
                            <th className="px-4 py-3 font-medium">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        {pages.length === 0 && (
                            <tr><td colSpan={5} className="px-4 py-8 text-center text-neutral-400">No pages.</td></tr>
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
                                <td className="px-4 py-3">{p.is_published ? 'Yes' : 'No'}</td>
                                <td className="px-4 py-3 text-neutral-500">{p.updated_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
