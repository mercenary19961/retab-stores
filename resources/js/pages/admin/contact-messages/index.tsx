import Button from '@/components/admin/button';
import Pagination from '@/components/admin/pagination';
import Select from '@/components/admin/select';
import StatusBadge from '@/components/admin/status-badge';
import CopyText from '@/components/copy-text';
import StatusPill from '@/components/status-pill';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { THEAD } from '@/lib/admin-ui';
import { Head, router } from '@inertiajs/react';
import { Check, ChevronDown, Mail, MessageCircle } from 'lucide-react';
import { useState } from 'react';

interface MessageRow {
    id: number;
    name: string;
    email: string;
    phone: string;
    whatsapp_url: string;
    inquiry_type: string;
    message: string;
    handled: boolean;
    created_at: string | null;
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

/** One tone per inquiry type so the list scans by colour. */
export default function ContactMessagesIndex({
    messages,
    filters,
    openCount = 0,
    inquiryTypes = [],
}: {
    messages: Paginator<MessageRow>;
    filters: { status: string | null; per_page: number };
    openCount?: number;
    inquiryTypes?: string[];
}) {
    const { t } = useAdminT();
    // Which rows are expanded. A set rather than a single id so staff can compare
    // two messages side by side without one collapsing the other.
    const [open, setOpen] = useState<number[]>([]);

    const toggle = (id: number) => setOpen((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

    const setStatus = (status: string) =>
        router.get(
            '/admin/contact-messages',
            { status: status || undefined, per_page: filters.per_page || undefined },
            { preserveState: true, preserveScroll: true },
        );

    const typeLabel = (type: string) => (inquiryTypes.includes(type) ? t(`admin.contactMessages.types.${type}`) : type);

    return (
        <AdminLayout title={t('admin.contactMessages.title')}>
            <Head title={t('admin.contactMessages.title')} />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-neutral-400">{t('admin.contactMessages.intro')}</p>
                <Select
                    value={filters.status ?? ''}
                    onChange={setStatus}
                    options={[
                        { value: '', label: t('admin.contactMessages.filterAll') },
                        {
                            value: 'open',
                            label: openCount > 0 ? `${t('admin.contactMessages.filterOpen')} (${openCount})` : t('admin.contactMessages.filterOpen'),
                        },
                        { value: 'handled', label: t('admin.contactMessages.filterHandled') },
                    ]}
                    className="w-full sm:w-52"
                />
            </div>

            <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="min-w-full text-sm">
                    <thead className={THEAD}>
                        <tr>
                            <th className="px-4 py-3 font-medium">{t('admin.contactMessages.cols.sender')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.contactMessages.cols.type')}</th>
                            <th className="px-4 py-3 font-medium">{t('admin.contactMessages.cols.when')}</th>
                            <th className="px-4 py-3 text-end font-medium">{t('admin.common.actions')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {messages.data.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-4 py-8 text-center text-neutral-400">
                                    {t('admin.contactMessages.empty')}
                                </td>
                            </tr>
                        )}
                        {messages.data.map((m) => {
                            const expanded = open.includes(m.id);

                            return [
                                <tr
                                    key={m.id}
                                    onClick={() => toggle(m.id)}
                                    className="cursor-pointer border-b border-neutral-100 last:border-0 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                                >
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <ChevronDown
                                                className={`h-4 w-4 shrink-0 text-neutral-400 transition-transform ${expanded ? 'rotate-180' : ''}`}
                                            />
                                            <span>
                                                <span className="block font-medium" dir="auto">
                                                    {m.name}
                                                </span>
                                                {/* Click-to-copy, and it swallows its own click so copying
                                                    the address doesn't also expand the row. This is the
                                                    RELIABLE way to reply: the mailto: button below depends
                                                    on the machine having a working mail handler. */}
                                                <span className="block text-xs text-neutral-500" onClick={(e) => e.stopPropagation()}>
                                                    <CopyText
                                                        value={m.email}
                                                        copyLabel={t('admin.common.copy')}
                                                        copiedLabel={t('admin.common.copied')}
                                                    />
                                                </span>
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge domain="inquiry" value={m.inquiry_type} label={typeLabel(m.inquiry_type)} />
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-neutral-500">{m.created_at ?? '—'}</td>
                                    {/* The row toggles the message body, so the actions cell swallows
                                        its own clicks — otherwise marking handled would also expand
                                        the row. Sits on the cell rather than the Button because the
                                        shared Button's onClick takes no event argument. */}
                                    <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                                        <div className="flex items-center justify-end">
                                            {m.handled ? (
                                                <StatusPill tone="done" icon={Check}>
                                                    {t('admin.contactMessages.handled')}
                                                </StatusPill>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    icon={Check}
                                                    onClick={() =>
                                                        router.post(`/admin/contact-messages/${m.id}/handle`, {}, { preserveScroll: true })
                                                    }
                                                >
                                                    {t('admin.contactMessages.markHandled')}
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>,
                                expanded && (
                                    <tr key={`${m.id}-body`} className="border-b border-neutral-100 dark:border-neutral-800">
                                        <td colSpan={4} className="bg-neutral-50 px-4 py-4 dark:bg-neutral-800/30">
                                            {/* dir="auto" so an Arabic message renders RTL and a Latin one LTR,
                                                decided per message rather than pinned to the admin's own locale. */}
                                            <p className="mb-3 whitespace-pre-wrap text-neutral-700 dark:text-neutral-200" dir="auto">
                                                {m.message}
                                            </p>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <a
                                                    href={`mailto:${m.email}`}
                                                    className="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 px-2.5 py-1.5 text-xs text-neutral-600 hover:bg-white dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                                >
                                                    <Mail className="h-3.5 w-3.5" /> {t('admin.contactMessages.replyEmail')}
                                                </a>
                                                <a
                                                    href={m.whatsapp_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 px-2.5 py-1.5 text-xs text-neutral-600 hover:bg-white dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                                >
                                                    <MessageCircle className="h-3.5 w-3.5" /> {t('admin.contactMessages.replyWhatsapp')}
                                                </a>
                                                <span className="font-mono text-xs text-neutral-500">
                                                    <CopyText
                                                        value={m.phone}
                                                        copyLabel={t('admin.common.copy')}
                                                        copiedLabel={t('admin.common.copied')}
                                                    />
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                ),
                            ];
                        })}
                    </tbody>
                </table>
            </div>

            <Pagination paginator={messages} perPage={filters.per_page} />
        </AdminLayout>
    );
}
