import StatusPill, { type StatusTone } from '@/components/status-pill';
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

const STATUS_TONES: Record<string, StatusTone> = {
    pending_payment: 'neutral',
    awaiting_confirmation: 'amber',
    confirmed: 'blue',
    shipped: 'indigo',
    delivered: 'green',
    cancelled: 'red',
    unavailable: 'red',
};

export const ORDER_STATUS_LABELS = STATUS_LABELS;

export default function OrderStatusBadge({ status }: { status: string }) {
    const { t } = useAdminT();

    return <StatusPill tone={STATUS_TONES[status] ?? 'neutral'}>{t(`status.${status}`, STATUS_LABELS[status] ?? status)}</StatusPill>;
}
