import { Head, router, useForm, usePage } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import UndoButton, { type UndoMeta } from '@/components/admin/undo-button';
import { useHighlightFields } from '@/hooks/use-highlight-fields';
import { useAdminT } from '@/i18n/use-admin-t';

const CONFIRM_WORD = 'RESET';

type Settings = Record<string, string | null>;

// Labels/hints/group headers are translated by key (admin.settings.fields.* etc.).
type Field = { key: string; type?: string; dir?: string; group?: string };

const FIELDS: Field[] = [
    { key: 'shipping_flat_fee', type: 'number' },
    { key: 'legal_name', dir: 'auto' },
    { key: 'bank_name', dir: 'auto' },
    { key: 'bank_beneficiary', dir: 'auto' },
    { key: 'bank_account' },
    { key: 'bank_iban' },
    // Footer / contact block — shown site-wide. Blank = fall back to the default (placeholder).
    { key: 'contact_phone', dir: 'ltr', group: 'footerContact' },
    { key: 'contact_email', type: 'email', dir: 'ltr' },
    { key: 'commercial_registration', dir: 'ltr' },
    { key: 'vat_number', dir: 'ltr' },
    { key: 'social_snapchat', type: 'url', dir: 'ltr' },
    { key: 'social_facebook', type: 'url', dir: 'ltr' },
    { key: 'social_instagram', type: 'url', dir: 'ltr' },
    { key: 'social_x', type: 'url', dir: 'ltr' },
    { key: 'social_linkedin', type: 'url', dir: 'ltr' },
];

export default function SettingsIndex({
    settings,
    defaults = {},
    undoMeta = null,
    canReset = false,
}: {
    settings: Settings;
    defaults?: Record<string, string>;
    undoMeta?: UndoMeta | null;
    canReset?: boolean;
}) {
    const { t } = useAdminT();
    useHighlightFields();
    const flash = (usePage().props as { flash?: { success?: string | null } }).flash;
    const { data, setData, put, processing, errors } = useForm(
        Object.fromEntries([
            ...FIELDS.map((f) => [f.key, settings[f.key] ?? '']),
            // Attention-beam toggle, kept as '1'/'0' so the whole form stays string-typed.
            ['admin_help_pulse', settings['admin_help_pulse'] === '0' ? '0' : '1'],
        ]) as Record<string, string>,
    );

    const [confirming, setConfirming] = useState(false);
    const [confirmText, setConfirmText] = useState('');

    // Deep link from the help drawer (/admin/settings#help-pulse): scroll to the
    // toggle and pulse it so the setting is easy to find.
    useEffect(() => {
        if (window.location.hash !== '#help-pulse') return;
        const el = document.getElementById('help-pulse');
        if (!el) return;
        el.scrollIntoView({ block: 'center' });
        el.classList.add('field-highlight');
    }, []);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/admin/settings', { preserveScroll: true });
    };

    const doReset = () => {
        router.post('/admin/settings/reset', {}, {
            preserveScroll: true,
            onFinish: () => {
                setConfirming(false);
                setConfirmText('');
            },
        });
    };

    return (
        <AdminLayout title={t('admin.settings.title')}>
            <Head title={t('admin.settings.title')} />

            {flash?.success && (
                <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {flash.success}
                </div>
            )}

            {undoMeta && (
                <div className="mb-4">
                    <UndoButton section="settings" undoMeta={undoMeta} />
                </div>
            )}

            <form onSubmit={submit} className="max-w-lg space-y-4 rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                <label id="help-pulse" className="flex items-start gap-3 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                    <input
                        type="checkbox"
                        checked={data.admin_help_pulse === '1'}
                        onChange={(e) => setData('admin_help_pulse', e.target.checked ? '1' : '0')}
                        className="mt-0.5 h-4 w-4 shrink-0 accent-brand-gold"
                    />
                    <span>
                        <span className="text-sm font-medium">{t('admin.settings.helpPulse.label')}</span>
                        <span className="block text-xs text-neutral-400">{t('admin.settings.helpPulse.hint')}</span>
                    </span>
                </label>

                {FIELDS.map((f) => {
                    const hint = t(`admin.settings.hints.${f.key}`, { defaultValue: '' });
                    return (
                        <div key={f.key}>
                            {f.group && (
                                <h2 className="mb-1 mt-4 border-t border-neutral-200 pt-4 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:border-neutral-800">
                                    {t(`admin.settings.groups.${f.group}`)}
                                </h2>
                            )}
                            <label className="block" id={`field-${f.key}`}>
                                <span className="text-sm text-neutral-500">{t(`admin.settings.fields.${f.key}`)}</span>
                                <input
                                    type={f.type ?? 'text'}
                                    step={f.type === 'number' ? '0.01' : undefined}
                                    dir={f.dir}
                                    value={data[f.key]}
                                    placeholder={defaults[f.key]}
                                    onChange={(e) => setData(f.key, e.target.value)}
                                    className="mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-950"
                                />
                                {hint && <span className="text-xs text-neutral-400">{hint}</span>}
                                {errors[f.key] && <span className="block text-xs text-red-500">{errors[f.key]}</span>}
                            </label>
                        </div>
                    );
                })}

                <Button type="submit" variant="primary" disabled={processing}>
                    {t('admin.settings.save')}
                </Button>
            </form>

            {canReset && (
                <section className="mt-8 max-w-lg rounded-lg border border-red-300 bg-red-50 p-5 dark:border-red-900/60 dark:bg-red-950/30">
                    <h2 className="flex items-center gap-2 font-bold text-red-700 dark:text-red-300">
                        <RotateCcw className="h-4 w-4" /> {t('admin.settings.reset.title')}
                    </h2>
                    <p className="mt-2 text-sm text-neutral-600 dark:text-neutral-300">{t('admin.settings.reset.lead')}</p>
                    <ul className="mt-2 space-y-1 text-xs">
                        <li className="text-amber-700 dark:text-amber-300">{t('admin.settings.reset.restores')}</li>
                        <li className="text-neutral-500 dark:text-neutral-400">{t('admin.settings.reset.keeps')}</li>
                    </ul>

                    {!confirming ? (
                        <div className="mt-4">
                            <Button variant="danger" icon={RotateCcw} onClick={() => setConfirming(true)}>
                                {t('admin.settings.reset.button')}
                            </Button>
                        </div>
                    ) : (
                        <div className="mt-4 space-y-3">
                            <label className="block">
                                <span className="text-sm text-neutral-600 dark:text-neutral-300">
                                    {t('admin.settings.reset.confirmPrompt', { word: CONFIRM_WORD })}
                                </span>
                                <input
                                    value={confirmText}
                                    onChange={(e) => setConfirmText(e.target.value)}
                                    autoFocus
                                    className="mt-1 w-full rounded border border-red-300 px-3 py-2 text-sm dark:border-red-900 dark:bg-neutral-950"
                                />
                            </label>
                            <div className="flex gap-2">
                                <Button variant="danger" disabled={confirmText !== CONFIRM_WORD} onClick={doReset}>
                                    {t('admin.settings.reset.confirm')}
                                </Button>
                                <Button variant="secondary" onClick={() => { setConfirming(false); setConfirmText(''); }}>
                                    {t('admin.settings.reset.cancel')}
                                </Button>
                            </div>
                        </div>
                    )}
                </section>
            )}
        </AdminLayout>
    );
}
