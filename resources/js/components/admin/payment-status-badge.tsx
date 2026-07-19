import { useAdminT } from '@/i18n/use-admin-t';

// Payment lifecycle colours, kept distinct from the order-status badge:
// paid = settled (green), authorized = held not captured (blue), pending =
// awaiting (amber), refunded = money back out (purple), failed = red.
const CLASSES: Record<string, string> = {
    paid: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    authorized: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    refunded: 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-200',
    partially_refunded: 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-200',
    voided: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300',
    failed: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200',
};

export default function PaymentStatusBadge({ status }: { status: string }) {
    const { t } = useAdminT();

    return (
        <span
            className={`inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ${
                CLASSES[status] ?? 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300'
            }`}
        >
            {t(`admin.paymentStatus.${status}`, status)}
        </span>
    );
}
