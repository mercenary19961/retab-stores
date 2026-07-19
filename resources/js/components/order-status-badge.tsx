import { useAdminT } from '@/i18n/use-admin-t';

const STATUS_LABELS: Record<string, string> = {
    pending_payment: 'Pending payment',
    awaiting_confirmation: 'Awaiting confirmation',
    confirmed: 'Confirmed',
    shipped: 'Shipped',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
    unavailable: 'Unavailable',
};

const STATUS_CLASSES: Record<string, string> = {
    pending_payment: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
    awaiting_confirmation: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    confirmed: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    shipped: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200',
    delivered: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200',
    unavailable: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200',
};

export const ORDER_STATUS_LABELS = STATUS_LABELS;

export default function OrderStatusBadge({ status }: { status: string }) {
    const { t } = useAdminT();
    return (
        <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_CLASSES[status] ?? ''}`}>
            {t(`status.${status}`, STATUS_LABELS[status] ?? status)}
        </span>
    );
}
