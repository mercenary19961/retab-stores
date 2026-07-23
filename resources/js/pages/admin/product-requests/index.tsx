import { Head, router } from '@inertiajs/react';
import { Check, ExternalLink } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import Select from '@/components/admin/select';
import { useAdminT } from '@/i18n/use-admin-t';

interface RequestRow {
    id: number;
    product: { name_ar: string; name_en: string | null; slug: string } | null;
    customer: string | null;
    phone: string | null;
    is_guest: boolean;
    handled: boolean;
    created_at: string | null;
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

export default function ProductRequestsIndex({
    requests,
    filters,
    openCount = 0,
}: {
    requests: Paginator<RequestRow>;
    filters: { status: string | null };
    openCount?: number;
}) {
    const { t, i18n } = useAdminT();
    const loc = (ar: string | null, en: string | null) => (i18n.language === 'en' && en ? en : (ar ?? '—'));

    const setStatus = (status: string) =>
        router.get('/admin/product-requests', { status: status || undefined }, { preserveState: true, preserveScroll: true });

    return (
        <AdminLayout title={t('admin.productRequests.title')}>
            <Head title={t('admin.productRequests.title')} />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-neutral-400">{t('admin.productRequests.intro')}</p>
                <Select
                    value={filters.status ?? ''}
                    onChange={setStatus}
                    options={[
                        { value: '', label: t('admin.productRequests.filterAll') },
                        { value: 'open', label: openCount > 0 ? `${t('admin.productRequests.filterOpen')} (${openCount})` : t('admin.productRequests.filterOpen') },
                        { value: 'handled', label: t('admin.productRequests.filterHandled') },
                    ]}
                    className="w-full sm:w-52"
                />
            </div>

            <div className="overflow-x-auto rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="min-w-full text-sm">
                    <thead className="border-b border-neutral-200 bg-neutral-50 text-left text-neutral-600 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-300">
                        <tr>
                            <th className="px-4 py-3 font-medium">{t('admin.productRequests.cols.product')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.productRequests.cols.customer')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.productRequests.cols.contact')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.productRequests.cols.when')}</th>
                            <th className="px-4 py-3 text-end font-medium">{t('admin.common.actions')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {requests.data.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-neutral-400">{t('admin.productRequests.empty')}</td>
                            </tr>
                        )}
                        {requests.data.map((r) => (
                            <tr key={r.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td className="px-4 py-3">
                                    {r.product ? (
                                        <a
                                            href={`/products/${r.product.slug}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            dir="auto"
                                            className="inline-flex items-center gap-1 text-brand-gold hover:underline"
                                        >
                                            {loc(r.product.name_ar, r.product.name_en)}
                                            <ExternalLink className="h-3.5 w-3.5" />
                                        </a>
                                    ) : (
                                        <span className="text-neutral-400">—</span>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    {r.is_guest ? (
                                        <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{t('admin.productRequests.guest')}</span>
                                    ) : (
                                        <span dir="auto">{r.customer ?? '—'}</span>
                                    )}
                                </td>
                                <td className="px-4 py-3 font-mono text-neutral-500" dir="ltr">{r.phone ?? '—'}</td>
                                <td className="px-4 py-3 whitespace-nowrap text-neutral-500">{r.created_at ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center justify-end">
                                        {r.handled ? (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800 dark:bg-green-950 dark:text-green-200">
                                                <Check className="h-3.5 w-3.5" /> {t('admin.productRequests.handled')}
                                            </span>
                                        ) : (
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                icon={Check}
                                                onClick={() => router.post(`/admin/product-requests/${r.id}/handle`, {}, { preserveScroll: true })}
                                            >
                                                {t('admin.productRequests.markHandled')}
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {requests.total > requests.data.length && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {requests.links.map((link, i) => (
                        <button
                            key={i}
                            type="button"
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                            className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-600 disabled:opacity-40 dark:text-neutral-300'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
