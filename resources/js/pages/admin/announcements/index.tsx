import Button from '@/components/admin/button';
import ConfirmDeleteButton from '@/components/admin/confirm-delete-button';
import Modal from '@/components/admin/modal';
import StatusBadge from '@/components/admin/status-badge';
import StatusToggle from '@/components/admin/status-toggle';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { CARD, THEAD } from '@/lib/admin-ui';
import { Head, router, useForm } from '@inertiajs/react';
import { Megaphone, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

interface Row {
    id: number;
    message_ar: string;
    message_en: string | null;
    link_url: string | null;
    link_label_ar: string | null;
    link_label_en: string | null;
    tone: string;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
    sort_order: number;
    state: string;
}

/** `datetime-local` wants `YYYY-MM-DDTHH:mm`; the server ships ISO-8601. */
const toInput = (iso: string | null) => (iso ? iso.slice(0, 16) : '');

export default function AnnouncementsIndex({ announcements, tones }: { announcements: Row[]; tones: string[] }) {
    const { t, i18n } = useAdminT();
    const [editing, setEditing] = useState<Row | null>(null);
    const [open, setOpen] = useState(false);

    const form = useForm({
        message_ar: '',
        message_en: '',
        link_url: '',
        link_label_ar: '',
        link_label_en: '',
        tone: 'info',
        is_active: true as boolean,
        starts_at: '',
        ends_at: '',
        sort_order: 0,
    });

    const openFor = (row: Row | null) => {
        setEditing(row);
        form.setData({
            message_ar: row?.message_ar ?? '',
            message_en: row?.message_en ?? '',
            link_url: row?.link_url ?? '',
            link_label_ar: row?.link_label_ar ?? '',
            link_label_en: row?.link_label_en ?? '',
            tone: row?.tone ?? 'info',
            is_active: row?.is_active ?? true,
            starts_at: toInput(row?.starts_at ?? null),
            ends_at: toInput(row?.ends_at ?? null),
            sort_order: row?.sort_order ?? 0,
        });
        form.clearErrors();
        setOpen(true);
    };

    const submit = () => {
        const done = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) form.put(`/admin/announcements/${editing.id}`, done);
        else form.post('/admin/announcements', done);
    };

    const field = (name: keyof typeof form.data, label: string, type = 'text') => (
        <label className="block">
            <span className="text-sm text-neutral-300">{label}</span>
            <input
                type={type}
                value={String(form.data[name] ?? '')}
                onChange={(e) => form.setData(name, (type === 'number' ? Number(e.target.value) : e.target.value) as never)}
                className="focus:border-brand-gold mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-white outline-none"
            />
            {form.errors[name] && <span className="text-xs text-red-400">{form.errors[name]}</span>}
        </label>
    );

    return (
        <AdminLayout title={t('admin.announcements.title')}>
            <Head title={t('admin.announcements.title')} />

            <div className="mb-4 flex items-start justify-between gap-4">
                <p className="max-w-2xl text-sm text-neutral-400">{t('admin.announcements.intro')}</p>
                <Button onClick={() => openFor(null)} icon={Plus}>
                    {t('admin.announcements.add')}
                </Button>
            </div>

            <div className={`${CARD} overflow-hidden`}>
                {announcements.length === 0 ? (
                    <div className="px-6 py-12 text-center">
                        <Megaphone className="mx-auto mb-3 h-8 w-8 text-neutral-600" />
                        <p className="font-medium text-neutral-300">{t('admin.announcements.emptyTitle')}</p>
                        <p className="mt-1 text-sm text-neutral-500">{t('admin.announcements.emptyHint')}</p>
                    </div>
                ) : (
                    <table className="w-full text-sm">
                        <thead className={THEAD}>
                            <tr>
                                <th className="px-4 py-3 font-medium">{t('admin.announcements.message')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.announcements.window')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.common.status')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.common.actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {announcements.map((a) => (
                                <tr key={a.id} className="border-b border-neutral-800 last:border-0">
                                    <td className="max-w-md px-4 py-3">
                                        <p className="truncate text-neutral-200" dir="auto">
                                            {i18n.language === 'en' && a.message_en ? a.message_en : a.message_ar}
                                        </p>
                                        {a.link_url && <p className="truncate text-xs text-neutral-500">{a.link_url}</p>}
                                    </td>
                                    <td className="px-4 py-3 text-neutral-400">
                                        {/* ⚠️ Blank means "no bound", which is the feature: no end
                                            date is the client's "leave it up until I take it down". */}
                                        <span className="block text-xs">
                                            {a.starts_at ? new Date(a.starts_at).toLocaleString() : t('admin.announcements.noStart')}
                                        </span>
                                        <span className="block text-xs">
                                            {a.ends_at ? new Date(a.ends_at).toLocaleString() : t('admin.announcements.noEnd')}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge domain="announcement" value={a.state} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <StatusToggle
                                                tone={a.is_active ? 'active' : 'stopped'}
                                                label={a.is_active ? t('admin.announcements.on') : t('admin.announcements.off')}
                                                url={`/admin/announcements/${a.id}/toggle`}
                                                method="post"
                                            />
                                            <Button variant="secondary" size="sm" icon={Pencil} onClick={() => openFor(a)}>
                                                {t('admin.common.edit')}
                                            </Button>
                                            <ConfirmDeleteButton
                                                itemName={a.message_ar}
                                                onConfirm={() => router.delete(`/admin/announcements/${a.id}`, { preserveScroll: true })}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            <Modal open={open} onClose={() => setOpen(false)} title={editing ? t('admin.announcements.edit') : t('admin.announcements.add')}>
                <div className="space-y-4">
                    {field('message_ar', t('admin.announcements.messageAr'))}
                    {/* Surfaces the two rules a client cannot infer from the form:
                        short copy reads, and only one shows at a time. */}
                    <p className="-mt-2 text-xs text-neutral-500">{t('admin.announcements.messageHint')}</p>
                    {field('message_en', t('admin.announcements.messageEn'))}

                    <div className="grid gap-4 sm:grid-cols-2">
                        {field('link_url', t('admin.announcements.linkUrl'))}
                        {field('link_label_ar', t('admin.announcements.linkLabelAr'))}
                    </div>
                    {field('link_label_en', t('admin.announcements.linkLabelEn'))}

                    <div>
                        <span className="text-sm text-neutral-300">{t('admin.announcements.tone')}</span>
                        <div className="mt-1 flex gap-2">
                            {tones.map((tone) => (
                                <button
                                    key={tone}
                                    type="button"
                                    onClick={() => form.setData('tone', tone)}
                                    className={`rounded-lg border px-3 py-1.5 text-sm transition-colors ${
                                        form.data.tone === tone
                                            ? 'border-brand-gold bg-brand-teal text-white'
                                            : 'border-neutral-700 text-neutral-300 hover:border-neutral-500'
                                    }`}
                                >
                                    {t(`admin.announcements.tones.${tone}`)}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {field('starts_at', t('admin.announcements.startsAt'), 'datetime-local')}
                        {field('ends_at', t('admin.announcements.endsAt'), 'datetime-local')}
                    </div>
                    {/* 🔑 States the rule rather than making the client infer it from
                        an empty field: this is the "keep it up until I hide it" case. */}
                    <p className="text-xs text-neutral-500">{t('admin.announcements.datesHint')}</p>

                    <label className="flex items-center gap-2">
                        <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                        <span className="text-sm text-neutral-300">{t('admin.announcements.isActive')}</span>
                    </label>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="secondary" onClick={() => setOpen(false)}>
                            {t('admin.common.cancel')}
                        </Button>
                        <Button onClick={submit} disabled={form.processing}>
                            {t('admin.common.save')}
                        </Button>
                    </div>
                </div>
            </Modal>
        </AdminLayout>
    );
}
