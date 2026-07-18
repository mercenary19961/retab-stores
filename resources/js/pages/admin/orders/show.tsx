import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import OrderDetailView, { type OrderCan, type OrderDetailData } from '@/components/admin/order-detail-view';
import { useAdminT } from '@/i18n/use-admin-t';

export default function OrderShow({ order, can }: { order: OrderDetailData; can: OrderCan }) {
    const { t } = useAdminT();
    const [busy, setBusy] = useState(false);

    const action = (verb: string, data: Record<string, string> = {}, confirmMsg?: string) => {
        if (confirmMsg && !window.confirm(confirmMsg)) return;
        router.post(`/admin/orders/${order.order_number}/${verb}`, data, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <AdminLayout>
            <Head title={t('admin.orders.show.headTitle', { number: order.order_number })} />

            <div className="mb-6">
                <Link href="/admin/orders" className="inline-flex items-center gap-1 text-sm text-neutral-500 hover:underline">
                    <ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {t('admin.nav.orders')}
                </Link>
                <h1 className="mt-1 text-2xl font-bold">
                    <span className="font-mono">{order.order_number}</span>
                </h1>
            </div>

            <OrderDetailView order={order} can={can} onAction={action} busy={busy} />
        </AdminLayout>
    );
}
