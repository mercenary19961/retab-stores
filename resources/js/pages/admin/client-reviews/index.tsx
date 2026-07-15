import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';

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
    const activeCount = reviews.filter((r) => r.is_active).length;

    return (
        <AdminLayout title="Client Reviews">
            <Head title="Client Reviews" />

            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-neutral-500">
                    {activeCount} active in the homepage pool · {reviews.length} total
                </p>
                <Link
                    href="/admin/client-reviews/create"
                    className="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-700 dark:bg-white dark:text-neutral-900"
                >
                    New review
                </Link>
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-sm">
                    <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Author</th>
                            <th className="px-4 py-3 font-medium">Review</th>
                            <th className="px-4 py-3 font-medium">Rating</th>
                            <th className="px-4 py-3 font-medium">Source</th>
                            <th className="px-4 py-3 font-medium">In pool</th>
                            <th className="px-4 py-3 font-medium">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        {reviews.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-8 text-center text-neutral-400">No reviews yet.</td></tr>
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
                                        {r.is_active ? 'Yes' : 'No'}
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
