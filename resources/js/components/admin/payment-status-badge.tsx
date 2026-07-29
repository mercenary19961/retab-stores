import StatusPill, { type StatusTone } from '@/components/status-pill';
import { useAdminT } from '@/i18n/use-admin-t';

// Payment lifecycle colours, kept distinct from the order-status badge:
// paid = settled (green), authorized = held not captured (blue), pending =
// awaiting (amber), refunded = money back out (purple), failed = red.
const TONES: Record<string, StatusTone> = {
    paid: 'green',
    authorized: 'blue',
    pending: 'amber',
    refunded: 'purple',
    partially_refunded: 'purple',
    voided: 'neutral',
    failed: 'red',
};

export default function PaymentStatusBadge({ status }: { status: string }) {
    const { t } = useAdminT();

    return <StatusPill tone={TONES[status] ?? 'neutral'}>{t(`admin.paymentStatus.${status}`, status)}</StatusPill>;
}
