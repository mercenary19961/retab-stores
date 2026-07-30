import { Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';

import Button from '@/components/admin/button';
import { iconFor, NOTIFICATION_TYPES, useNotificationText, type NotificationItem } from '@/components/admin/notification-content';
import Pagination, { type Paginator } from '@/components/admin/pagination';
import Select from '@/components/admin/select';
import StatusPill from '@/components/status-pill';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { relativeTimeFromIso } from '@/lib/relative-time';

/**
 * Full notification history for the signed-in staff member — the bell only carries
 * the latest 10. Rows render through the SAME payload→text mapping as the bell
 * (useNotificationText) so the two can't drift, and clicking one reuses the bell's
 * open route: marks it read, then redirects to the subject.
 *
 * ⚠️ The paginator arrives as `entries`, not `notifications` — the latter is the
 * shared bell prop, and a page prop of that name would shadow it and break the
 * bell on this page.
 */
export default function NotificationsIndex({
    entries,
    filters,
}: {
    entries: Paginator<NotificationItem>;
    filters: { status: string | null; type: string | null; per_page: number };
}) {
    const { t, i18n } = useAdminT();
    const { titleFor, bodyFor } = useNotificationText();

    const applyFilters = (next: { status?: string | null; type?: string | null }) =>
        router.get(
            '/admin/notifications',
            {
                status: (next.status ?? filters.status) || undefined,
                type: (next.type ?? filters.type) || undefined,
                per_page: filters.per_page || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );

    const markAllRead = () => router.post('/admin/notifications/read-all', {}, { preserveScroll: true });

    return (
        <AdminLayout title={t('admin.notifications.history.title')}>
            <Head title={t('admin.notifications.history.title')} />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-neutral-400">{t('admin.notifications.history.intro')}</p>

                <div className="flex flex-wrap items-center gap-2">
                    <Select
                        value={filters.status ?? ''}
                        onChange={(status) => applyFilters({ status })}
                        options={[
                            { value: '', label: t('admin.notifications.history.filterAll') },
                            { value: 'unread', label: t('admin.notifications.history.filterUnread') },
                            { value: 'read', label: t('admin.notifications.history.filterRead') },
                        ]}
                        className="w-full sm:w-40"
                    />
                    <Select
                        value={filters.type ?? ''}
                        onChange={(type) => applyFilters({ type })}
                        options={[
                            { value: '', label: t('admin.notifications.history.filterAnyType') },
                            ...NOTIFICATION_TYPES.map((type) => ({ value: type, label: t(`admin.notifications.types.${type}`) })),
                        ]}
                        className="w-full sm:w-48"
                    />
                    <Button size="sm" variant="secondary" icon={Check} onClick={markAllRead}>
                        {t('admin.notifications.markAllRead')}
                    </Button>
                </div>
            </div>

            <div className="divide-y divide-neutral-100 overflow-hidden rounded-lg border border-neutral-200 bg-white dark:divide-neutral-800 dark:border-neutral-800 dark:bg-neutral-900">
                {entries.data.length === 0 && <p className="px-4 py-10 text-center text-sm text-neutral-400">{t('admin.notifications.history.empty')}</p>}

                {entries.data.map((entry) => {
                    const Icon = iconFor(entry.data.type);
                    const body = bodyFor(entry.data);

                    return (
                        <button
                            key={entry.id}
                            type="button"
                            onClick={() => router.visit(`/admin/notifications/${entry.id}`)}
                            className={`flex w-full items-start gap-3 px-4 py-3 text-start transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-800/60 ${
                                entry.read ? '' : 'bg-brand-gold/5 dark:bg-brand-gold/5'
                            }`}
                        >
                            <span className="text-brand-gold mt-0.5 shrink-0 rounded-lg bg-neutral-100 p-1.5 dark:bg-neutral-800">
                                <Icon className="h-4 w-4" />
                            </span>

                            <span className="min-w-0 flex-1">
                                <span className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm font-medium text-neutral-800 dark:text-neutral-100">{titleFor(entry.data)}</span>
                                    {!entry.read && (
                                        <StatusPill tone="amber" dot={false}>
                                            {t('admin.notifications.history.unread')}
                                        </StatusPill>
                                    )}
                                </span>
                                {body && (
                                    <span className="mt-0.5 block truncate text-xs text-neutral-500 dark:text-neutral-400" dir="auto">
                                        {body}
                                    </span>
                                )}
                            </span>

                            {entry.created_at && (
                                <span className="shrink-0 text-[11px] whitespace-nowrap text-neutral-400" title={new Date(entry.created_at).toLocaleString()}>
                                    {relativeTimeFromIso(entry.created_at, i18n.language)}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            <Pagination paginator={entries} perPage={filters.per_page} />
        </AdminLayout>
    );
}
