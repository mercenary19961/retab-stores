import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Landmark, Phone, RotateCcw, Share2, SlidersHorizontal, Store, type LucideIcon } from 'lucide-react';
import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import UndoButton, { type UndoMeta } from '@/components/admin/undo-button';
import { useHighlightFields } from '@/hooks/use-highlight-fields';
import { useAdminT } from '@/i18n/use-admin-t';

const CONFIRM_WORD = 'RESET';

type Settings = Record<string, string | null>;

// Labels/hints are translated by key (admin.settings.fields.*, .hints.*).
// `wide` fields span the full 2-column grid row inside a card.
type FieldDef = { key: string; type?: string; dir?: string; wide?: boolean };
type SectionDef = { key: string; icon: LucideIcon; fields: FieldDef[] };

const SECTIONS: SectionDef[] = [
    {
        key: 'store',
        icon: Store,
        fields: [
            { key: 'shipping_flat_fee', type: 'number' },
            { key: 'legal_name', dir: 'auto', wide: true },
        ],
    },
    {
        key: 'bank',
        icon: Landmark,
        fields: [
            { key: 'bank_name', dir: 'auto' },
            { key: 'bank_beneficiary', dir: 'auto' },
            { key: 'bank_account', dir: 'ltr' },
            { key: 'bank_iban', dir: 'ltr', wide: true },
        ],
    },
    {
        key: 'contact',
        icon: Phone,
        fields: [
            { key: 'contact_phone', type: 'tel', dir: 'ltr' },
            { key: 'contact_email', type: 'email', dir: 'ltr' },
            { key: 'commercial_registration', dir: 'ltr' },
            { key: 'vat_number', dir: 'ltr' },
        ],
    },
    {
        key: 'social',
        icon: Share2,
        fields: [
            { key: 'social_snapchat', type: 'url', dir: 'ltr' },
            { key: 'social_facebook', type: 'url', dir: 'ltr' },
            { key: 'social_instagram', type: 'url', dir: 'ltr' },
            { key: 'social_x', type: 'url', dir: 'ltr' },
            { key: 'social_linkedin', type: 'url', dir: 'ltr' },
        ],
    },
];

const ALL_KEYS = SECTIONS.flatMap((s) => s.fields.map((f) => f.key));

const INPUT =
    'w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm transition-colors placeholder:text-neutral-400 focus:border-brand-gold focus:outline-none focus:ring-1 focus:ring-brand-gold dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100';

// `mb-6 break-inside-avoid` = masonry spacing; cards flow into two balanced
// columns on wide screens (CSS multi-column).
const CARD =
    'mb-6 break-inside-avoid scroll-mt-4 rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 sm:p-6';

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
    const { t, i18n } = useAdminT();
    const rtl = i18n.language === 'ar';
    useHighlightFields();
    const flash = (usePage().props as { flash?: { success?: string | null } }).flash;
    const { data, setData, put, processing, errors, isDirty } = useForm(
        Object.fromEntries([
            ...ALL_KEYS.map((k) => [k, settings[k] ?? '']),
            // Attention-beam toggle, kept as '1'/'0' so the whole form stays string-typed.
            ['admin_help_pulse', settings['admin_help_pulse'] === '0' ? '0' : '1'],
        ]) as Record<string, string>,
    );

    const [confirming, setConfirming] = useState(false);
    const [confirmText, setConfirmText] = useState('');

    // Deep link from the help drawer (/admin/settings#help-pulse): scroll to the
    // preferences card and pulse it so the setting is easy to find.
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

    const renderField = (f: FieldDef): ReactNode => {
        const hint = t(`admin.settings.hints.${f.key}`, { defaultValue: '' });
        return (
            <label key={f.key} id={`field-${f.key}`} className={`block ${f.wide ? 'sm:col-span-2' : ''}`}>
                <span className="mb-1 block text-sm font-medium text-neutral-600 dark:text-neutral-300">
                    {t(`admin.settings.fields.${f.key}`)}
                </span>
                <input
                    type={f.type ?? 'text'}
                    step={f.type === 'number' ? '0.01' : undefined}
                    dir={f.dir}
                    value={data[f.key]}
                    placeholder={defaults[f.key]}
                    onChange={(e) => setData(f.key, e.target.value)}
                    className={INPUT}
                />
                {hint && <span className="mt-1 block text-xs text-neutral-400">{hint}</span>}
                {errors[f.key] && <span className="mt-1 block text-xs text-red-500">{errors[f.key]}</span>}
            </label>
        );
    };

    const sectionHeader = (Icon: LucideIcon, titleKey: string) => (
        <div className="mb-5 flex items-start gap-3">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-teal/15 text-brand-gold">
                <Icon className="h-5 w-5" />
            </div>
            <div>
                <h2 className="font-semibold text-neutral-900 dark:text-neutral-100">
                    {t(`admin.settings.sections.${titleKey}.title`)}
                </h2>
                <p className="text-sm text-neutral-500">{t(`admin.settings.sections.${titleKey}.desc`)}</p>
            </div>
        </div>
    );

    const pulseOn = data.admin_help_pulse === '1';

    return (
        <AdminLayout title={t('admin.settings.title')}>
            <Head title={t('admin.settings.title')} />

            {flash?.success && (
                <div className="mb-4 rounded-lg border border-green-900 bg-green-950 px-4 py-3 text-sm text-green-200">
                    {flash.success}
                </div>
            )}

            {undoMeta && (
                <div className="mb-4">
                    <UndoButton section="settings" undoMeta={undoMeta} />
                </div>
            )}

            <form onSubmit={submit} className="space-y-6">
                <div className="gap-6 xl:columns-2">
                    {SECTIONS.map((s) => (
                        <section key={s.key} className={CARD}>
                            {sectionHeader(s.icon, s.key)}
                            <div className="grid gap-4 sm:grid-cols-2">{s.fields.map(renderField)}</div>
                        </section>
                    ))}

                    {/* Admin-panel preferences (not storefront) — holds the help beam toggle. */}
                    <section id="help-pulse" className={CARD}>
                        {sectionHeader(SlidersHorizontal, 'preferences')}
                        <div className="flex items-center justify-between gap-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                            <div className="min-w-0">
                                <span className="text-sm font-medium text-neutral-700 dark:text-neutral-200">
                                    {t('admin.settings.helpPulse.label')}
                                </span>
                                <p className="text-xs text-neutral-400">{t('admin.settings.helpPulse.hint')}</p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                aria-checked={pulseOn}
                                aria-label={t('admin.settings.helpPulse.label')}
                                onClick={() => setData('admin_help_pulse', pulseOn ? '0' : '1')}
                                className={`relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors ${
                                    pulseOn ? 'bg-brand-teal' : 'bg-neutral-300 dark:bg-neutral-600'
                                }`}
                            >
                                <span
                                    aria-hidden
                                    className="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                                    style={{ insetInlineStart: '0.125rem', transform: pulseOn ? `translateX(${rtl ? '-1.25rem' : '1.25rem'})` : 'translateX(0)' }}
                                />
                            </button>
                        </div>
                    </section>
                </div>

                {/* Just the save button — disabled until something actually changes. */}
                <div className="flex items-center gap-3">
                    <Button type="submit" variant="primary" disabled={processing || !isDirty}>
                        {t('admin.settings.save')}
                    </Button>
                    <span className="text-xs text-neutral-400">{t('admin.settings.saveHint')}</span>
                </div>
            </form>

            {canReset && (
                <section className="mt-6 max-w-3xl rounded-xl border border-red-300 bg-red-50 p-5 shadow-sm dark:border-red-900/60 dark:bg-red-950/30 sm:p-6">
                    <div className="flex items-start gap-3">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-500/15 text-red-600 dark:text-red-300">
                            <RotateCcw className="h-5 w-5" />
                        </div>
                        <div className="min-w-0">
                            <h2 className="font-semibold text-red-700 dark:text-red-300">{t('admin.settings.reset.title')}</h2>
                            <p className="mt-1 text-sm text-neutral-600 dark:text-neutral-300">{t('admin.settings.reset.lead')}</p>
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
                                            className="mt-1 w-full max-w-xs rounded-lg border border-red-300 px-3 py-2 text-sm dark:border-red-900 dark:bg-neutral-950"
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
                        </div>
                    </div>
                </section>
            )}
        </AdminLayout>
    );
}
