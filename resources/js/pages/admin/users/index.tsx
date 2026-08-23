import Button from '@/components/admin/button';
import ChangePasswordForm from '@/components/admin/change-password-form';
import ConfirmDeleteButton from '@/components/admin/confirm-delete-button';
import Modal from '@/components/admin/modal';
import CopyText from '@/components/copy-text';
import StatusPill from '@/components/status-pill';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { emptyMap, matchingPreset, type PermissionMap } from '@/lib/permissions';
import { type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, KeyRound, RefreshCw, ShieldCheck, UserPlus, UserRound } from 'lucide-react';
import { useState } from 'react';

type Perms = Record<string, Record<string, boolean>>;
type Role = 'admin' | 'editor';

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
    role: Role;
    created_at: string | null;
    // Whether a reset link could ever reach them. False for the staff accounts
    // deliberately created on a non-routable address, which is exactly who this
    // page's reset action exists for.
    can_self_recover: boolean;
    // Null for a non-admin viewer — only an admin is sent the grid.
    permissions: Perms | null;
}

/** What the VIEWER may do here, resolved server-side from their permissions. */
interface Can {
    manageStaff: boolean;
    resetPasswords: boolean;
}

export default function UsersIndex({
    staff,
    schema,
    presets,
    can,
}: {
    staff: Staff[];
    schema: Record<string, string[]> | null;
    defaults: Perms | null;
    presets: Record<string, Perms> | null;
    can: Can;
}) {
    const { t } = useAdminT();

    const [selectedId, setSelectedId] = useState<number | null>(staff[0]?.id ?? null);
    // Local editable copy of each editor's permission map.
    const [edits, setEdits] = useState<Record<number, Perms>>({});
    const [adding, setAdding] = useState(false);
    // The staff member whose role is being changed, held while the confirm dialog
    // is open (null = closed).
    const [roleTarget, setRoleTarget] = useState<Staff | null>(null);
    // Same pattern for the password reset. The password is generated when the
    // dialog opens and held here so Regenerate can replace it.
    const [resetTarget, setResetTarget] = useState<Staff | null>(null);
    const [resetPw, setResetPw] = useState('');
    const [resetting, setResetting] = useState(false);

    const selected = staff.find((s) => s.id === selectedId) ?? null;
    const addForm = useForm<{ name: string; email: string; password: string; role: Role }>({
        name: '',
        email: '',
        password: '',
        role: 'editor',
    });

    // The password section only appears on your OWN row: changing it requires the
    // current password, which is by definition nobody else's to supply.
    const { auth } = usePage<SharedData>().props;
    const isMe = selected !== null && selected.id === auth.user?.id;

    // Everyone with back-office access is on this page, so the count is exact.
    const adminCount = staff.filter((s) => s.role === 'admin').length;

    // `permissions` is null for a non-admin viewer; the grid that reads this is
    // admin-only, so an empty map is only ever a type-level fallback.
    const permsFor = (s: Staff): Perms => edits[s.id] ?? s.permissions ?? {};

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
     * Every chip's map, keyed by chip name. The named ones are expanded
     * server-side from the same SCHEMA the grid renders, so a section added later
     * is included automatically rather than silently missing (a missing section
     * falls back to DEFAULTS, i.e. it would GRANT where the preset means to deny).
     * "Clear all" is derived here because it needs no server knowledge.
     */
    const chipMaps: Record<string, PermissionMap> = schema ? { ...(presets ?? {}), none: emptyMap(schema) } : {};

    const applyPreset = (name: string) => {
        if (!selected || !chipMaps[name]) return;
        setEdits((prev) => ({ ...prev, [selected.id]: chipMaps[name] }));
    };

    /**
     * Which chip the grid currently EQUALS, so the highlight describes the live
     * state rather than remembering a click. Pressing a chip lights it, drifting
     * away by hand puts it out, and hand-toggling back onto a preset lights it
     * again. Null when the grid is a combination no chip describes, which is the
     * normal case once an admin has fine-tuned anything.
     */
    const activePreset = selected && schema ? matchingPreset(schema, permsFor(selected), chipMaps) : null;

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

    /**
     * Why a role change may be refused, or null when it is allowed.
     *
     * Mirrors the server's two guards, IN THE SAME ORDER, so the button explains
     * itself instead of failing on submit. The server still enforces both — this
     * is the hint, not the rule.
     */
    const roleBlockedReason = (s: Staff): string | null => {
        // Count first: the sole admin looking at their own row wants "promote
        // someone else first", not "not your own role", which tells them nothing.
        if (s.role === 'admin' && adminCount <= 1) return t('admin.users.role.lastAdminBlocked');
        if (s.id === auth.user?.id) return t('admin.users.role.selfBlocked');

        return null;
    };

    const confirmRoleChange = () => {
        if (!roleTarget) return;
        const next: Role = roleTarget.role === 'admin' ? 'editor' : 'admin';
        router.put(`/admin/users/${roleTarget.id}/role`, { role: next }, { preserveScroll: true, onFinish: () => setRoleTarget(null) });
    };

    /**
     * Why setting this person's password is refused, or null when it is allowed.
     *
     * Mirrors the server's guards in Admin\UserController::resetPassword, in the
     * same order. The server still enforces them — this is the hint, not the rule.
     */
    const resetBlockedReason = (s: Staff): string | null => {
        // 🔴 The escalation guard: an editor who could set an admin's password
        // could simply sign in as that admin.
        if (s.role === 'admin' && !can.manageStaff) return t('admin.users.passwordReset.adminBlocked');

        return null;
    };

    const openReset = (s: Staff) => {
        setResetPw(newPassword());
        setResetTarget(s);
    };

    const confirmReset = () => {
        if (!resetTarget) return;
        setResetting(true);
        router.post(
            `/admin/users/${resetTarget.id}/reset-password`,
            { password: resetPw },
            {
                preserveScroll: true,
                onSuccess: () => setResetTarget(null),
                onFinish: () => setResetting(false),
            },
        );
    };

    /**
     * Open with a clean slate and a fresh password, so the credential is ready to
     * copy and a cancelled attempt never leaks into the next one.
     */
    const openAdd = () => {
        addForm.setData({ name: '', email: '', password: newPassword(), role: 'editor' });
        addForm.clearErrors();
        setAdding(true);
    };

    const closeAdd = () => setAdding(false);

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

    /**
     * The role row, shown for admins and editors alike so it sits in one place
     * whoever you select.
     *
     * When a change is refused the button is not rendered at all and the reason
     * takes its place — a dead control the admin has to hover to understand is
     * worse than a sentence saying why.
     */
    const roleCard = (s: Staff) => {
        // Changing a role changes who has access, so it is admin-only. A viewer
        // who is merely permitted to reset passwords never sees this row.
        if (!can.manageStaff) return null;
        const blocked = roleBlockedReason(s);

        return (
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-800 bg-neutral-900 px-5 py-4">
                <div className="min-w-0">
                    <p className="text-xs text-neutral-500">{t('admin.users.role.label')}</p>
                    <p className="font-medium text-neutral-100">{t(`admin.users.roles.${s.role}`)}</p>
                </div>
                {blocked ? (
                    <p className="max-w-xs text-xs text-neutral-500">{blocked}</p>
                ) : (
                    <Button size="sm" variant={s.role === 'admin' ? 'warning' : 'secondary'} onClick={() => setRoleTarget(s)}>
                        {t(s.role === 'admin' ? 'admin.users.role.makeEditor' : 'admin.users.role.makeAdmin')}
                    </Button>
                )}
            </div>
        );
    };

    /**
     * The "set a new password for this person" row.
     *
     * Rendered only for a viewer holding `staff.reset_password`, and it states
     * WHY the action is theirs to do: an account on a non-routable address has
     * no reset-by-email at all, so this page is the only way back in for them.
     */
    const passwordCard = (s: Staff) => {
        if (!can.resetPasswords) return null;
        // Never on your OWN row. The real self-service form (which asks for the
        // current password) is already rendered just below, so a card here would
        // be a second password control describing you in the third person and
        // pointing at a button it does not offer.
        if (s.id === auth.user?.id) return null;
        const blocked = resetBlockedReason(s);

        return (
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-800 bg-neutral-900 px-5 py-4">
                <div className="min-w-0">
                    <p className="text-xs text-neutral-500">{t('admin.users.passwordReset.label')}</p>
                    <p className="max-w-md text-sm text-neutral-300">
                        {t(s.can_self_recover ? 'admin.users.passwordReset.canSelfRecover' : 'admin.users.passwordReset.noSelfRecovery')}
                    </p>
                </div>
                {blocked ? (
                    <p className="max-w-xs text-xs text-neutral-500">{blocked}</p>
                ) : (
                    <Button size="sm" variant="secondary" icon={KeyRound} onClick={() => openReset(s)}>
                        {t('admin.users.passwordReset.reset')}
                    </Button>
                )}
            </div>
        );
    };

    return (
        <AdminLayout title={t('admin.users.title')}>
            <Head title={t('admin.users.title')} />

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <p className="text-sm text-neutral-400">{t('admin.users.subtitle')}</p>
                {/* Creating staff is a role, not a permission — see routes/admin.php. */}
                {can.manageStaff && (
                    <Button variant="primary" icon={UserPlus} onClick={openAdd}>
                        {t('admin.users.addStaff')}
                    </Button>
                )}
            </div>

            {/* In a dialog rather than inline: opening the form used to push the whole
                staff list and detail panel down the page, so the thing you were about
                to configure moved out from under you. The shared admin Modal also
                brings Esc + backdrop dismissal for free. */}
            <Modal open={adding} onClose={closeAdd} title={t('admin.users.addStaff')}>
                <form
                    onSubmit={submitAdd}
                    // ⚠️ `autoComplete="off"` on the form is not enough on its own for
                    // Chrome — see the password field below — but it does stop the
                    // name/email being filled from the signed-in admin's own profile.
                    autoComplete="off"
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

                    {/* Role. Two chips rather than a <select>: it is a binary choice
                        whose consequence differs enough to be worth spelling out
                        under it, and the note changes with the selection. */}
                    <div className="mt-4">
                        <span className="text-xs text-neutral-400">{t('admin.users.role.label')}</span>
                        <div className="mt-1.5 flex flex-wrap gap-2">
                            {(['editor', 'admin'] as const).map((r) => (
                                <button key={r} type="button" onClick={() => addForm.setData('role', r)} className={chip(addForm.data.role === r)}>
                                    {t(`admin.users.roles.${r}`)}
                                </button>
                            ))}
                        </div>
                        <p className={`mt-1.5 text-xs ${addForm.data.role === 'admin' ? 'text-amber-400/80' : 'text-neutral-500'}`}>
                            {t(addForm.data.role === 'admin' ? 'admin.users.role.adminNote' : 'admin.users.role.editorNote')}
                        </p>
                        {addForm.errors.role && <span className="text-xs text-red-400">{addForm.errors.role}</span>}
                    </div>

                    {/* Actions on the dialog's trailing edge, which is the convention the
                        panel's other modals follow. */}
                    <div className="mt-6 flex items-center justify-end gap-2 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                        <Button type="button" variant="secondary" onClick={closeAdd}>
                            {t('admin.users.cancel')}
                        </Button>
                        {/* `primary`, not `success`: this is the form's main action, not
                            the confirmation of a risky one (which is what the green
                            success variant means elsewhere — approve a return, apply a
                            stock import). */}
                        <Button type="submit" variant="primary" disabled={addForm.processing || !addForm.data.name || !addForm.data.email}>
                            {t(addForm.data.role === 'admin' ? 'admin.users.createAdmin' : 'admin.users.create')}
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Role changes get a confirm step where the permission grid does not:
                this one is not a display preference, it either hands somebody every
                section of the store or takes it away. */}
            <Modal
                open={roleTarget !== null}
                onClose={() => setRoleTarget(null)}
                title={t(roleTarget?.role === 'admin' ? 'admin.users.role.confirmDemoteTitle' : 'admin.users.role.confirmPromoteTitle')}
            >
                {roleTarget && (
                    <>
                        <p className="text-sm text-neutral-600 dark:text-neutral-300">
                            {t(roleTarget.role === 'admin' ? 'admin.users.role.confirmDemoteBody' : 'admin.users.role.confirmPromoteBody', {
                                name: roleTarget.name ?? roleTarget.email,
                            })}
                        </p>
                        <div className="mt-6 flex items-center justify-end gap-2 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                            <Button variant="secondary" onClick={() => setRoleTarget(null)}>
                                {t('admin.users.cancel')}
                            </Button>
                            <Button variant={roleTarget.role === 'admin' ? 'warning' : 'primary'} onClick={confirmRoleChange}>
                                {t(roleTarget.role === 'admin' ? 'admin.users.role.makeEditor' : 'admin.users.role.makeAdmin')}
                            </Button>
                        </div>
                    </>
                )}
            </Modal>

            {/* Reset another staff member's password.
                A confirm step rather than a one-click flip, because unlike the
                reversible toggles elsewhere in the panel this locks the person
                out of their current session until someone hands them the new
                password. */}
            <Modal open={resetTarget !== null} onClose={() => setResetTarget(null)} title={t('admin.users.passwordReset.confirmTitle')}>
                {resetTarget && (
                    <>
                        <p className="text-sm text-neutral-600 dark:text-neutral-300">
                            {t('admin.users.passwordReset.confirmBody', { name: resetTarget.name ?? resetTarget.email })}
                        </p>

                        {/* Generated, never typed — same reasoning as the add form:
                            an admin should not be inventing a password for someone
                            else, and it has to be readable to be passed on. */}
                        <label className="mt-4 block">
                            <span className="text-xs text-neutral-400">{t('admin.users.passwordReset.newPassword')}</span>
                            <div className="mt-1 flex flex-wrap items-center gap-2">
                                <input
                                    type="text"
                                    readOnly
                                    value={resetPw}
                                    autoComplete="new-password"
                                    className={`${inputCls} mt-0 flex-1 font-mono tracking-wide`}
                                    dir="ltr"
                                />
                                <Button type="button" variant="secondary" icon={RefreshCw} onClick={() => setResetPw(newPassword())}>
                                    {t('admin.users.regenerate')}
                                </Button>
                                <CopyText
                                    value={resetPw}
                                    display={t('admin.users.copyPassword')}
                                    copyLabel={t('admin.users.copyPassword')}
                                    copiedLabel={t('admin.users.copied')}
                                    className="rounded-lg border border-neutral-700 px-3 py-2 text-sm font-semibold text-neutral-200 transition-colors hover:bg-neutral-800"
                                />
                            </div>
                        </label>

                        <p className="mt-3 text-xs text-neutral-500">{t('admin.users.passwordReset.signsOutHint')}</p>

                        <div className="mt-6 flex items-center justify-end gap-2 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                            <Button variant="secondary" onClick={() => setResetTarget(null)}>
                                {t('admin.users.cancel')}
                            </Button>
                            <Button variant="primary" onClick={confirmReset} disabled={resetting}>
                                {t('admin.users.passwordReset.reset')}
                            </Button>
                        </div>
                    </>
                )}
            </Modal>

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
                                <StatusPill
                                    tone={s.role === 'admin' ? 'active' : 'idle'}
                                    icon={s.role === 'admin' ? ShieldCheck : UserRound}
                                    className="shrink-0"
                                >
                                    {t(`admin.users.roles.${s.role}`)}
                                </StatusPill>
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
                        <p className="rounded-xl border border-neutral-800 bg-neutral-900 p-8 text-center text-sm text-neutral-500">
                            {t('admin.users.noEditors')}
                        </p>
                    ) : selected.role === 'admin' ? (
                        <div className="space-y-6">
                            <div className="rounded-xl border border-neutral-800 bg-neutral-900 p-8 text-center">
                                <ShieldCheck className="mx-auto mb-3 h-9 w-9 text-purple-400" />
                                <p className="font-medium text-neutral-200">{selected.name}</p>
                                <p className="mt-1 text-sm text-neutral-500">{t('admin.users.adminFullAccess')}</p>
                            </div>
                            {roleCard(selected)}
                            {passwordCard(selected)}
                            {isMe && (
                                <div className="rounded-xl border border-neutral-800 bg-neutral-900">
                                    <ChangePasswordForm />
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {roleCard(selected)}
                            {/* Without the grid (a non-admin viewer) nothing else
                                would name the person being looked at. */}
                            {!can.manageStaff && (
                                <div className="rounded-xl border border-neutral-800 bg-neutral-900 px-5 py-4">
                                    <h2 className="truncate font-semibold text-neutral-100">{selected.name ?? selected.email}</h2>
                                    <p className="truncate text-xs text-neutral-500" dir="ltr">
                                        {selected.email}
                                    </p>
                                </div>
                            )}
                            {passwordCard(selected)}
                            {can.manageStaff && schema && (
                                <div className="rounded-xl border border-neutral-800 bg-neutral-900">
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

                                    {/* Presets: several dozen switches is a lot to set
                                    one at a time, and the real staff roles are few.
                                    These only fill the grid — nothing is saved until
                                    the admin presses Save — so the stored value is
                                    always the map they actually confirmed. */}
                                    <div className="flex flex-wrap items-center gap-2 border-b border-neutral-800 px-5 py-3">
                                        <span className="text-xs text-neutral-500">{t('admin.users.presetsLabel')}</span>
                                        {Object.keys(chipMaps).map((name) => {
                                            const active = name === activePreset;

                                            return (
                                                <button
                                                    key={name}
                                                    type="button"
                                                    onClick={() => applyPreset(name)}
                                                    className={`${chip(active)} inline-flex items-center gap-1.5`}
                                                    // Reflects the grid, not the last click, so a screen
                                                    // reader hears what the colour is saying.
                                                    aria-pressed={active}
                                                    title={active ? t('admin.users.presetsMatches') : undefined}
                                                >
                                                    {/* The tick is what separates "this is what you
                                                        currently have" from the identically-styled
                                                        permission switches in the rows below. */}
                                                    {active && <Check className="h-3.5 w-3.5" />}
                                                    {t(`admin.users.presets.${name}`)}
                                                </button>
                                            );
                                        })}
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
                            {isMe && (
                                <div className="rounded-xl border border-neutral-800 bg-neutral-900">
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
