import { router, usePage } from '@inertiajs/react';
import { Bell, Check, RotateCcw, ShoppingBag, Sparkles, type LucideIcon } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useAdminT } from '@/i18n/use-admin-t';
import { relativeTimeFromIso } from '@/lib/relative-time';

interface NotificationData {
    type?: string;
    order_number?: string | null;
    total?: string;
    currency?: string;
    reason?: string;
    product_name?: string | null;
    contact?: string | null;
    url?: string;
}

interface NotificationItem {
    id: string;
    read: boolean;
    created_at: string | null;
    data: NotificationData;
}

interface NotificationsProp {
    unread: number;
    items: NotificationItem[];
}

// Per notification `type`, the leading icon in the dropdown.
const ICONS: Record<string, LucideIcon> = {
    new_order: ShoppingBag,
    return_requested: RotateCcw,
    product_requested: Sparkles,
};

/**
 * The admin navbar notification bell. Reads the shared `notifications` prop
 * (unread count + latest items) and renders each item's title/message from
 * client-side i18n so it follows the admin language toggle. Clicking an item
 * marks it read server-side and redirects to its target.
 */
export default function NotificationBell() {
    const { t, i18n } = useAdminT();
    const page = usePage();
    const notifications = (page.props as { notifications?: NotificationsProp | null }).notifications;
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    const close = useCallback(() => setOpen(false), []);

    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => ref.current && !ref.current.contains(e.target as Node) && close();
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && close();
        window.addEventListener('mousedown', onDown);
        window.addEventListener('keydown', onKey);
        return () => {
            window.removeEventListener('mousedown', onDown);
            window.removeEventListener('keydown', onKey);
        };
    }, [open, close]);

    // Close the dropdown after any Inertia navigation.
    useEffect(() => router.on('navigate', () => setOpen(false)), []);

    if (!notifications) return null;

    const { unread, items } = notifications;

    const titleFor = (d: NotificationData): string => {
        if (d.type === 'new_order') return t('admin.notifications.items.newOrder.title', { order: d.order_number ?? '' });
        if (d.type === 'return_requested') return t('admin.notifications.items.returnRequested.title', { order: d.order_number ?? '' });
        if (d.type === 'product_requested') return t('admin.notifications.items.productRequested.title');
        return t('admin.notifications.items.generic.title');
    };
    const bodyFor = (d: NotificationData): string => {
        if (d.type === 'new_order') return t('admin.notifications.items.newOrder.body', { total: d.total ?? '', currency: d.currency ?? '' });
        if (d.type === 'return_requested') return d.reason || t('admin.notifications.items.returnRequested.body');
        if (d.type === 'product_requested') return t('admin.notifications.items.productRequested.body', { product: d.product_name ?? '' });
        return '';
    };

    const markAllRead = () => router.post('/admin/notifications/read-all', {}, { preserveScroll: true, preserveState: true });

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-label={t('admin.notifications.title')}
                className="relative flex items-center rounded-lg p-1.5 text-neutral-300 transition-colors hover:bg-neutral-800 hover:text-white"
            >
                <Bell className="h-5 w-5" />
                {unread > 0 && (
                    <span className="absolute -top-0.5 end-0 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white">
                        {unread > 9 ? '9+' : unread}
                    </span>
                )}
            </button>

            {open && (
                <div
                    className="absolute end-0 mt-2 w-80 overflow-hidden rounded-xl border border-neutral-700 bg-neutral-900 text-neutral-200 shadow-2xl"
                    style={{ zIndex: 60 }}
                >
                    <div className="flex items-center justify-between border-b border-neutral-800 px-4 py-2.5">
                        <span className="text-sm font-semibold text-neutral-100">{t('admin.notifications.title')}</span>
                        {unread > 0 && (
                            <button type="button" onClick={markAllRead} className="flex items-center gap-1 text-xs text-brand-gold transition-colors hover:underline">
                                <Check className="h-3.5 w-3.5" /> {t('admin.notifications.markAllRead')}
                            </button>
                        )}
                    </div>

                    <div className="max-h-96 overflow-y-auto">
                        {items.length === 0 ? (
                            <p className="px-4 py-10 text-center text-sm text-neutral-500">{t('admin.notifications.empty')}</p>
                        ) : (
                            items.map((n) => {
                                const Icon = ICONS[n.data.type ?? ''] ?? Bell;
                                const body = bodyFor(n.data);
                                return (
                                    <button
                                        key={n.id}
                                        type="button"
                                        onClick={() => router.visit(`/admin/notifications/${n.id}`)}
                                        className={`flex w-full items-start gap-3 border-b border-neutral-800/70 px-4 py-3 text-start transition-colors last:border-0 hover:bg-neutral-800 ${n.read ? '' : 'bg-neutral-800/40'}`}
                                    >
                                        <span className="mt-0.5 shrink-0 rounded-lg bg-neutral-800 p-1.5 text-brand-gold">
                                            <Icon className="h-4 w-4" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-center gap-2">
                                                <span className="truncate text-sm font-medium text-neutral-100">{titleFor(n.data)}</span>
                                                {!n.read && <span className="h-2 w-2 shrink-0 rounded-full bg-brand-gold" />}
                                            </span>
                                            {body && (
                                                <span className="mt-0.5 block truncate text-xs text-neutral-400" dir="auto">
                                                    {body}
                                                </span>
                                            )}
                                            {n.created_at && <span className="mt-1 block text-[11px] text-neutral-500">{relativeTimeFromIso(n.created_at, i18n.language)}</span>}
                                        </span>
                                    </button>
                                );
                            })
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
