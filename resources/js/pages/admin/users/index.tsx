import Button from '@/components/admin/button';
import ChangePasswordForm from '@/components/admin/change-password-form';
import ConfirmDeleteButton from '@/components/admin/confirm-delete-button';
import CopyText from '@/components/copy-text';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { RefreshCw, ShieldCheck, UserPlus } from 'lucide-react';
import { useState } from 'react';

type Perms = Record<string, Record<string, boolean>>;

const inputCls = 'mt-1 w-full rounded border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm';

/**
 * A strong password the admin never has to invent.
 *
 * `crypto.getRandomValues`, not `Math.random` — this is a real credential, and
 * Math.random is neither uniform nor unpredictable. The alphabet omits the
 * characters that get misread when a password is passed on by hand or over
 * WhatsApp (0/O, 1/l/I), which is exactly how these will be delivered.
 */
function newPassword(length = 16): string {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%&*';
    const bytes = new Uint32Array(length);
    crypto.getRandomValues(bytes);

    return Array.from(bytes, (b) => alphabet[b % alphabet.length]).join('');
}

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
    presets,
}: {
    staff: Staff[];
    schema: Record<string, string[]>;
    defaults: Perms;
    presets: Record<string, Perms>;
}) {
    const { t } = useAdminT();

    const [selectedId, setSelectedId] = useState<number | null>(staff[0]?.id ?? null);
    // Local editable copy of each editor's permission map.
    const [edits, setEdits] = useState<Record<number, Perms>>({});
    const [adding, setAdding] = useState(false);

    const selected = staff.find((s) => s.id === selectedId) ?? null;
    const addForm = useForm({ name: '', email: '', password: '' });

    // The password section only appears on your OWN row: changing it requires the
    // current password, which is by definition nobody else's to supply.
    const { auth } = usePage<SharedData>().props;
    const isMe = selected !== null && selected.id === auth.user?.id;

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

    /**
     * Load a preset into the grid. Built server-side from the same SCHEMA the grid
     * renders, so a section added later is included automatically rather than
     * silently missing (a missing section falls back to DEFAULTS, i.e. it would
     * GRANT where the preset means to deny).
     */
    const applyPreset = (name: string) => {
        if (!selected) return;
        const map =
            presets[name] ??
            // "none" isn't a server preset — it's every switch off, which is the
            // useful starting point when granting only one or two sections.
            Object.fromEntries(Object.entries(schema).map(([s, actions]) => [s, Object.fromEntries(actions.map((a) => [a, false]))]));

        setEdits((prev) => ({ ...prev, [selected.id]: map }));
    };

    const savePerms = () => {
        if (!selected) return;
        router.put(`/admin/users/${selected.id}/permissions`, { permissions: permsFor(selected) }, { preserveScroll: true });
    };

    const removeEditor = (s: Staff) => {
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
                <Button
                    variant="primary"
                    icon={UserPlus}
                    onClick={() => {
                        // Seed a fresh password each time the form opens, so it is
                        // ready to copy and there is never a blank credential field.
                        if (!adding) addForm.setData({ name: '', email: '', password: newPassword() });
                        setAdding((a) => !a);
                    }}
                >
                    {t('admin.users.addEditor')}
                </Button>
            </div>

            {adding && (
                <form
                    onSubmit={submitAdd}
                    // ⚠️ `autoComplete="off"` on the form is not enough on its own for
                    // Chrome — see the password field below — but it does stop the
                    // name/email being filled from the signed-in admin's own profile.
                    autoComplete="off"
                    className="mb-6 max-w-3xl rounded-lg border border-neutral-800 bg-neutral-900 p-4"
                >
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="block">
                            <span className="text-xs text-neutral-400">{t('admin.users.name')}</span>
                            <input
                                value={addForm.data.name}
                                onChange={(e) => addForm.setData('name', e.target.value)}
                                autoComplete="off"
                                className={inputCls}
                            />
                            {addForm.errors.name && <span className="text-xs text-red-400">{addForm.errors.name}</span>}
                        </label>
                        <label className="block">
                            <span className="text-xs text-neutral-400">{t('admin.users.email')}</span>
                            <input
                                type="email"
                                dir="ltr"
                                value={addForm.data.email}
                                onChange={(e) => addForm.setData('email', e.target.value)}
                                autoComplete="off"
                                className={inputCls}
                            />
                            {addForm.errors.email && <span className="text-xs text-red-400">{addForm.errors.email}</span>}
                        </label>
                    </div>

                    {/* 🔑 The password is GENERATED, not typed.
                        Three problems at once: the admin had to invent a password for
                        someone else (they pick weak ones), it had to be communicated
                        anyway, and a `type="password"` field next to an email is
                        exactly what Chrome's password manager treats as a login form —
                        so it filled the signed-in admin's OWN saved credentials into
                        the new-editor form and painted the fields white. It is shown
                        in clear because the admin has to pass it on, and it is useless
                        to anyone who cannot already create staff accounts. */}
                    <div className="mt-3">
                        <span className="text-xs text-neutral-400">{t('admin.users.password')}</span>
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <input
                                readOnly
                                dir="ltr"
                                value={addForm.data.password}
                                // `new-password` is the documented signal that stops
                                // Chrome offering a SAVED credential here.
                                autoComplete="new-password"
                                onFocus={(e) => e.currentTarget.select()}
                                className={`${inputCls} mt-0 min-w-0 flex-1 font-mono tracking-wider`}
                            />
                            <Button type="button" variant="secondary" icon={RefreshCw} onClick={() => addForm.setData('password', newPassword())}>
                                {t('admin.users.regenerate')}
                            </Button>
                            {/* `display` overrides the rendered text, so the button reads
                                "Copy password" instead of repeating the value already
                                shown in the field beside it. */}
                            <CopyText
                                value={addForm.data.password}
                                display={t('admin.users.copyPassword')}
                                copyLabel={t('admin.users.copyPassword')}
                                copiedLabel={t('admin.users.copied')}
                                className="rounded-lg border border-neutral-700 px-3 py-2 text-sm font-semibold text-neutral-200 transition-colors hover:bg-neutral-800"
                            />
                        </div>
                        <p className="mt-1.5 text-xs text-neutral-500">{t('admin.users.passwordHint')}</p>
                        {addForm.errors.password && <span className="text-xs text-red-400">{addForm.errors.password}</span>}
                    </div>

                    <div className="mt-4 flex items-center gap-2">
                        {/* `primary`, not `success`: this is the form's main action, not
                            the confirmation of a risky one (which is what the green
                            success variant means elsewhere — approve a return, apply a
                            stock import). */}
                        <Button type="submit" variant="primary" disabled={addForm.processing || !addForm.data.name || !addForm.data.email}>
                            {t('admin.users.create')}
                        </Button>
                        <Button type="button" variant="secondary" onClick={() => setAdding(false)}>
                            {t('admin.users.cancel')}
                        </Button>
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
                                        s.role === 'admin' ? 'bg-purple-500/20 text-purple-300' : 'bg-neutral-800 text-neutral-400'
                                    }`}
                                >
                                    {t(`admin.users.roles.${s.role}`)}
                                </span>
                            </div>
                            <div className="mt-0.5 truncate text-xs text-neutral-500" dir="ltr">
                                {s.email}
                            </div>
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
                        <div className="space-y-6">
                            <div className="rounded-lg border border-neutral-800 bg-neutral-900 p-8 text-center">
                                <ShieldCheck className="mx-auto mb-3 h-9 w-9 text-purple-400" />
                                <p className="font-medium text-neutral-200">{selected.name}</p>
                                <p className="mt-1 text-sm text-neutral-500">{t('admin.users.adminFullAccess')}</p>
                            </div>
                            {isMe && (
                                <div className="rounded-lg border border-neutral-800 bg-neutral-900">
                                    <ChangePasswordForm />
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-6">
                            <div className="rounded-lg border border-neutral-800 bg-neutral-900">
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-800 px-5 py-3">
                                    <div className="min-w-0">
                                        <h2 className="truncate font-semibold text-neutral-100">{selected.name}</h2>
                                        <p className="text-xs text-neutral-500">{t('admin.users.hint')}</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button size="sm" variant="secondary" onClick={savePerms}>
                                            {t('admin.users.save')}
                                        </Button>
                                        <ConfirmDeleteButton
                                            itemName={selected.name ?? selected.email}
                                            label={t('admin.users.remove')}
                                            onConfirm={() => removeEditor(selected)}
                                        />
                                    </div>
                                </div>

                                {/* Presets: 14 sections and 33 switches is a lot to set
                                    one at a time, and the real staff roles are few.
                                    These only fill the grid — nothing is saved until
                                    the admin presses Save — so the stored value is
                                    always the map they actually confirmed. */}
                                <div className="flex flex-wrap items-center gap-2 border-b border-neutral-800 px-5 py-3">
                                    <span className="text-xs text-neutral-500">{t('admin.users.presetsLabel')}</span>
                                    {Object.keys(presets).map((name) => (
                                        <button key={name} type="button" onClick={() => applyPreset(name)} className={chip(false)}>
                                            {t(`admin.users.presets.${name}`)}
                                        </button>
                                    ))}
                                    <button type="button" onClick={() => applyPreset('none')} className={chip(false)}>
                                        {t('admin.users.presets.none')}
                                    </button>
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
                            {isMe && (
                                <div className="rounded-lg border border-neutral-800 bg-neutral-900">
                                    <ChangePasswordForm />
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
