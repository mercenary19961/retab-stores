import { Head, router, useForm } from '@inertiajs/react';
import { ShieldCheck, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import { useAdminT } from '@/i18n/use-admin-t';

type Perms = Record<string, Record<string, boolean>>;

interface Staff {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'editor';
    created_at: string | null;
    permissions: Perms;
}

export default function UsersIndex({
    staff,
    schema,
}: {
    staff: Staff[];
    schema: Record<string, string[]>;
    defaults: Perms;
}) {
    const { t } = useAdminT();

    const [selectedId, setSelectedId] = useState<number | null>(staff[0]?.id ?? null);
    // Local editable copy of each editor's permission map.
    const [edits, setEdits] = useState<Record<number, Perms>>({});
    const [adding, setAdding] = useState(false);

    const selected = staff.find((s) => s.id === selectedId) ?? null;
    const addForm = useForm({ name: '', email: '', password: '' });

    const permsFor = (s: Staff): Perms => edits[s.id] ?? s.permissions;

    const toggle = (section: string, action: string) => {
        if (!selected) return;
        const base = permsFor(selected);
        setEdits((prev) => ({
            ...prev,
            [selected.id]: {
                ...base,
                [section]: { ...base[section], [action]: !base[section]?.[action] },
            },
        }));
    };

    const savePerms = () => {
        if (!selected) return;
        router.put(`/admin/users/${selected.id}/permissions`, { permissions: permsFor(selected) }, { preserveScroll: true });
    };

    const removeEditor = (s: Staff) => {
        if (!window.confirm(t('admin.users.removeConfirm'))) return;
        router.delete(`/admin/users/${s.id}`, {
            preserveScroll: true,
            onSuccess: () => setSelectedId((cur) => (cur === s.id ? null : cur)),
        });
    };

    const submitAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post('/admin/users', {
            preserveScroll: true,
            onSuccess: () => {
                addForm.reset();
                setAdding(false);
            },
        });
    };

    const chip = (on: boolean) =>
        `rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
            on
                ? 'border-brand-teal/50 bg-brand-teal/20 text-brand-teal'
                : 'border-neutral-700 bg-neutral-800 text-neutral-400 hover:border-neutral-500'
        }`;

    return (
        <AdminLayout title={t('admin.users.title')}>
            <Head title={t('admin.users.title')} />

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <p className="text-sm text-neutral-400">{t('admin.users.subtitle')}</p>
                <Button variant="primary" icon={UserPlus} onClick={() => setAdding((a) => !a)}>
                    {t('admin.users.addEditor')}
                </Button>
            </div>

            {adding && (
                <form onSubmit={submitAdd} className="mb-6 grid max-w-3xl gap-3 rounded-lg border border-neutral-800 bg-neutral-900 p-4 sm:grid-cols-4">
                    <label className="block sm:col-span-1">
                        <span className="text-xs text-neutral-400">{t('admin.users.name')}</span>
                        <input
                            value={addForm.data.name}
                            onChange={(e) => addForm.setData('name', e.target.value)}
                            className="mt-1 w-full rounded border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm"
                        />
                        {addForm.errors.name && <span className="text-xs text-red-400">{addForm.errors.name}</span>}
                    </label>
                    <label className="block sm:col-span-1">
                        <span className="text-xs text-neutral-400">{t('admin.users.email')}</span>
                        <input
                            type="email"
                            dir="ltr"
                            value={addForm.data.email}
                            onChange={(e) => addForm.setData('email', e.target.value)}
                            className="mt-1 w-full rounded border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm"
                        />
                        {addForm.errors.email && <span className="text-xs text-red-400">{addForm.errors.email}</span>}
                    </label>
                    <label className="block sm:col-span-1">
                        <span className="text-xs text-neutral-400">{t('admin.users.password')}</span>
                        <input
                            type="password"
                            dir="ltr"
                            value={addForm.data.password}
                            onChange={(e) => addForm.setData('password', e.target.value)}
                            className="mt-1 w-full rounded border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm"
                        />
                        {addForm.errors.password && <span className="text-xs text-red-400">{addForm.errors.password}</span>}
                    </label>
                    <div className="flex items-end gap-2 sm:col-span-1">
                        <Button type="submit" variant="success" disabled={addForm.processing || !addForm.isDirty}>{t('admin.users.create')}</Button>
                        <Button type="button" variant="secondary" onClick={() => setAdding(false)}>{t('admin.users.cancel')}</Button>
                    </div>
                </form>
            )}

            <div className="flex flex-col gap-6 lg:flex-row">
                {/* Staff list */}
                <div className="w-full shrink-0 space-y-1 lg:w-64">
                    {staff.map((s) => (
                        <button
                            key={s.id}
                            type="button"
                            onClick={() => setSelectedId(s.id)}
                            className={`w-full rounded-lg border px-4 py-3 text-start transition-colors ${
                                selectedId === s.id
                                    ? 'border-brand-gold/50 bg-brand-gold/10'
                                    : 'border-neutral-800 bg-neutral-900 hover:border-neutral-700'
                            }`}
                        >
                            <div className="flex items-center justify-between gap-2">
                                <span className="truncate font-medium text-neutral-100">{s.name ?? s.email}</span>
                                <span
                                    className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] ${
                                        s.role === 'admin'
                                            ? 'bg-purple-500/20 text-purple-300'
                                            : 'bg-neutral-800 text-neutral-400'
                                    }`}
                                >
                                    {t(`admin.users.roles.${s.role}`)}
                                </span>
                            </div>
                            <div className="mt-0.5 truncate text-xs text-neutral-500" dir="ltr">{s.email}</div>
                        </button>
                    ))}
                </div>

                {/* Detail panel */}
                <div className="min-w-0 flex-1">
                    {!selected ? (
                        <p className="rounded-lg border border-neutral-800 bg-neutral-900 p-8 text-center text-sm text-neutral-500">
                            {t('admin.users.noEditors')}
                        </p>
                    ) : selected.role === 'admin' ? (
                        <div className="rounded-lg border border-neutral-800 bg-neutral-900 p-8 text-center">
                            <ShieldCheck className="mx-auto mb-3 h-9 w-9 text-purple-400" />
                            <p className="font-medium text-neutral-200">{selected.name}</p>
                            <p className="mt-1 text-sm text-neutral-500">{t('admin.users.adminFullAccess')}</p>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-neutral-800 bg-neutral-900">
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-800 px-5 py-3">
                                <div className="min-w-0">
                                    <h2 className="truncate font-semibold text-neutral-100">{selected.name}</h2>
                                    <p className="text-xs text-neutral-500">{t('admin.users.hint')}</p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button size="sm" variant="secondary" onClick={savePerms}>{t('admin.users.save')}</Button>
                                    <Button size="sm" variant="danger" icon={Trash2} onClick={() => removeEditor(selected)}>{t('admin.users.remove')}</Button>
                                </div>
                            </div>

                            <div className="divide-y divide-neutral-800">
                                {Object.entries(schema).map(([section, actions]) => (
                                    <div key={section} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                        <span className="font-medium text-neutral-200">{t(`admin.users.sections.${section}`)}</span>
                                        <div className="flex flex-wrap gap-2">
                                            {actions.map((action) => (
                                                <button
                                                    key={action}
                                                    type="button"
                                                    onClick={() => toggle(section, action)}
                                                    className={chip(!!permsFor(selected)[section]?.[action])}
                                                >
                                                    {t(`admin.users.actions.${action}`)}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
