import { Head } from '@inertiajs/react';
import { useLocalized } from '@/lib/localize';
import StoreLayout from '@/layouts/store-layout';

interface Page {
    slug: string;
    title_ar: string;
    title_en: string | null;
    body_ar: string;
    body_en: string | null;
}

export default function ContentPage({ page }: { page: Page }) {
    const localized = useLocalized();

    return (
        <StoreLayout>
            <Head title={localized(page, 'title')} />

            <div className="mx-auto max-w-2xl">
                <h1 className="mb-6 text-2xl font-bold">{localized(page, 'title')}</h1>
                <div className="whitespace-pre-wrap leading-relaxed text-gray-700">
                    {localized(page, 'body')}
                </div>
            </div>
        </StoreLayout>
    );
}
