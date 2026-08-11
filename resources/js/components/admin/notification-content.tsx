import { Bell, Mail, RotateCcw, ShoppingBag, Sparkles, type LucideIcon } from 'lucide-react';

import { useAdminT } from '@/i18n/use-admin-t';

export interface NotificationData {
    type?: string;
    order_number?: string | null;
    total?: string;
    currency?: string;
    reason?: string;
    product_name?: string | null;
    contact?: string | null;
    name?: string | null;
    url?: string;
}

export interface NotificationItem {
    id: string;
    read: boolean;
    created_at: string | null;
    data: NotificationData;
}

/** Every notification `type` the server can store, for the history page's filter. */
export const NOTIFICATION_TYPES = ['new_order', 'return_requested', 'product_requested', 'contact_message_received'] as const;

const ICONS: Record<string, LucideIcon> = {
    new_order: ShoppingBag,
    return_requested: RotateCcw,
    product_requested: Sparkles,
    contact_message_received: Mail,
};

/** Leading icon for a type; a plain bell for anything unrecognised. */
export function iconFor(type?: string): LucideIcon {
    return ICONS[type ?? ''] ?? Bell;
}

/**
 * Title/body renderers for a notification payload.
 *
 * The server deliberately stores STRUCTURED data rather than prose so the text
 * follows the admin's language toggle (see NewOrderNotification) — which means the
 * payload→text mapping lives on the client. Shared by the navbar bell and the
 * history page so a new notification type can never render in one and not the
 * other. A hook rather than plain functions purely so callers don't have to pass
 * `t` around.
 */
export function useNotificationText() {
    const { t } = useAdminT();

    const titleFor = (d: NotificationData): string => {
        if (d.type === 'new_order') return t('admin.notifications.items.newOrder.title', { order: d.order_number ?? '' });
        if (d.type === 'return_requested') return t('admin.notifications.items.returnRequested.title', { order: d.order_number ?? '' });
        if (d.type === 'product_requested') return t('admin.notifications.items.productRequested.title');
        if (d.type === 'contact_message_received') return t('admin.notifications.items.contactMessage.title', { name: d.name ?? '' });
        return t('admin.notifications.items.generic.title');
    };

    const bodyFor = (d: NotificationData): string => {
        if (d.type === 'new_order') return t('admin.notifications.items.newOrder.body', { total: d.total ?? '', currency: d.currency ?? '' });
        if (d.type === 'return_requested') return d.reason || t('admin.notifications.items.returnRequested.body');
        if (d.type === 'product_requested') return t('admin.notifications.items.productRequested.body', { product: d.product_name ?? '' });
        if (d.type === 'contact_message_received') return t('admin.notifications.items.contactMessage.body');
        return '';
    };

    return { titleFor, bodyFor };
}
