import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import ReturnDetailView, { type RefundPreview, type ReturnDetail, type ReturnOrderSummary } from '@/components/admin/return-detail-view';
import { useAdminT } from '@/i18n/use-admin-t';

export default function ReturnShow({
    orderReturn,
    order,
    refundPreview,
}: {
    orderReturn: ReturnDetail;
    order: ReturnOrderSummary;
    refundPreview: RefundPreview;
}) {
    const { t } = useAdminT();
    const [busy, setBusy] = useState(false);

    const act = (action: string, payload: Record<string, string | boolean>) => {
        router.post(`/admin/returns/${orderReturn.id}/${action}`, payload, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <AdminLayout title={t('admin.returns.show.headTitle', { id: orderReturn.id })}>
            <Head title={t('admin.returns.show.headTitle', { id: orderReturn.id })} />

            <div className="mb-4">
                <Link href="/admin/returns" className="inline-flex items-center gap-1 text-sm text-neutral-500 hover:underline">
                    <ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {t('admin.nav.returns')}
                </Link>
            </div>

            <ReturnDetailView orderReturn={orderReturn} order={order} refundPreview={refundPreview} onAction={act} busy={busy} />
        </AdminLayout>
    );
}
