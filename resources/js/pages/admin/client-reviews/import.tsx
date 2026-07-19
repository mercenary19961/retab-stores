import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Upload } from 'lucide-react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import { useAdminT } from '@/i18n/use-admin-t';

const EXAMPLE = `Mohammad Ahmad | 5 | Great variety of Saudi dates, very fresh.
Sarah Al-Otaibi | 5 | Beautiful packaging and fast delivery.
خالد الزهراني | 4 | تمور فاخرة وخدمة ممتازة، أنصح بها.`;

export default function ClientReviewsImport() {
    const { t } = useAdminT();
    const { data, setData, post, processing, errors, isDirty } = useForm({ data: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/client-reviews/import');
    };

    return (
        <AdminLayout title={t('admin.reviews.import.title')}>
            <Head title={t('admin.reviews.import.title')} />

            <div className="mb-4">
                <Link href="/admin/client-reviews" className="inline-flex items-center gap-1 text-sm text-neutral-500 underline">
                    <ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {t('admin.reviews.title')}
                </Link>
            </div>

            <form onSubmit={submit} className="max-w-3xl space-y-4 rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                <div className="rounded-md bg-neutral-50 p-4 text-sm text-neutral-600 dark:bg-neutral-950 dark:text-neutral-300">
                    <p className="font-semibold">{t('admin.reviews.import.formatIntro')}</p>
                    <p className="mt-1 font-mono text-xs">{t('admin.reviews.import.formatLine')}</p>
                    <ul className="mt-2 list-inside list-disc text-xs text-neutral-500">
                        <li>{t('admin.reviews.import.note1')}</li>
                        <li>{t('admin.reviews.import.note2')}</li>
                        <li>{t('admin.reviews.import.note3')}</li>
                    </ul>
                </div>

                <label className="block">
                    <span className="text-sm text-neutral-500">{t('admin.reviews.import.reviewsLabel')}</span>
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

                <Button type="submit" variant="primary" icon={Upload} disabled={processing || !isDirty}>
                    {t('admin.reviews.import.submit')}
                </Button>
            </form>
        </AdminLayout>
    );
}
