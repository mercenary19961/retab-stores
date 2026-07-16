import { Head, Link, useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';

const EXAMPLE = `Mohammad Ahmad | 5 | Great variety of Saudi dates, very fresh.
Sarah Al-Otaibi | 5 | Beautiful packaging and fast delivery.
خالد الزهراني | 4 | تمور فاخرة وخدمة ممتازة، أنصح بها.`;

export default function ClientReviewsImport() {
    const { data, setData, post, processing, errors } = useForm({ data: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/client-reviews/import');
    };

    return (
        <AdminLayout title="Import reviews">
            <Head title="Import reviews" />

            <div className="mb-4">
                <Link href="/admin/client-reviews" className="text-sm text-neutral-500 underline">← Client reviews</Link>
            </div>

            <form onSubmit={submit} className="max-w-3xl space-y-4 rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                <div className="rounded-md bg-neutral-50 p-4 text-sm text-neutral-600 dark:bg-neutral-950 dark:text-neutral-300">
                    <p className="font-semibold">Paste one review per line, in the format:</p>
                    <p className="mt-1 font-mono text-xs">Author | Rating (1–5) | Review text</p>
                    <ul className="mt-2 list-inside list-disc text-xs text-neutral-500">
                        <li>Rating defaults to 5 if left blank; language (Arabic / English) is detected automatically.</li>
                        <li>Imported reviews are added as <strong>active</strong> (in the homepage pool). Blank lines are skipped.</li>
                        <li>Avoid the <code>|</code> character inside the review text.</li>
                    </ul>
                </div>

                <label className="block">
                    <span className="text-sm text-neutral-500">Reviews *</span>
                    <textarea
                        dir="auto"
                        rows={14}
                        value={data.data}
                        onChange={(e) => setData('data', e.target.value)}
                        placeholder={EXAMPLE}
                        className="mt-1 w-full rounded border border-neutral-300 px-3 py-2 font-mono text-sm dark:border-neutral-700 dark:bg-neutral-950"
                    />
                    {errors.data && <span className="block text-xs text-red-500">{errors.data}</span>}
                </label>

                <Button type="submit" variant="primary" icon={Upload} disabled={processing}>
                    Import reviews
                </Button>
            </form>
        </AdminLayout>
    );
}
