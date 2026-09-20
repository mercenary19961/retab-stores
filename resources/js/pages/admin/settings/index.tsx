import Button from '@/components/admin/button';
import PageHeader from '@/components/admin/page-header';
import UndoButton, { type UndoMeta } from '@/components/admin/undo-button';
import { useHighlightFields } from '@/hooks/use-highlight-fields';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { CreditCard, Landmark, Phone, RotateCcw, Share2, SlidersHorizontal, Store, type LucideIcon } from 'lucide-react';
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';

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

// Which payment methods checkout offers. Bank transfer leads because it is the
// one that needs no gateway, so it is the method a store can always fall back to.
const PAYMENT_METHODS = ['bank_transfer', 'card', 'tamara'] as const;
const PAYMENT_KEYS = PAYMENT_METHODS.map((m) => `payment_${m}_enabled`);

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
    const { data, setData, put, processing, errors, isDirty } = useForm(
        Object.fromEntries([
            ...ALL_KEYS.map((k) => [k, settings[k] ?? '']),
            // Attention-beam toggle, kept as '1'/'0' so the whole form stays string-typed.
            ['admin_help_pulse', settings['admin_help_pulse'] === '0' ? '0' : '1'],
            // Payment methods. Unset means ON — matching PaymentMethod::enabled(),
            // so a store that has never opened this page keeps offering all three.
            ...PAYMENT_KEYS.map((k) => [k, settings[k] === '0' ? '0' : '1']),
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
        router.post(
            '/admin/settings/reset',
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setConfirming(false);
                    setConfirmText('');
                },
            },
        );
    };

    const renderField = (f: FieldDef): ReactNode => {
        const hint = t(`admin.settings.hints.${f.key}`, { defaultValue: '' });
        return (
            <label key={f.key} id={`field-${f.key}`} className={`block ${f.wide ? 'sm:col-span-2' : ''}`}>
                <span className="mb-1 block text-sm font-medium text-neutral-600 dark:text-neutral-300">{t(`admin.settings.fields.${f.key}`)}</span>
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
            <div className="bg-brand-teal/15 text-brand-gold flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
                <Icon className="h-5 w-5" />
            </div>
            <div>
                <h2 className="font-semibold text-neutral-900 dark:text-neutral-100">{t(`admin.settings.sections.${titleKey}.title`)}</h2>
                <p className="text-sm text-neutral-500">{t(`admin.settings.sections.${titleKey}.desc`)}</p>
            </div>
        </div>
    );

    /**
     * One '1'/'0' setting as a switch. Shared by the help beam and the payment
     * methods so the two can't drift apart visually.
     *
     * The knob is positioned with `insetInlineStart` and a direction-aware
     * translate rather than an `rtl:` utility — inside the admin shell an `rtl:`
     * variant fires whenever the STOREFRONT is in Arabic, which throws the knob
     * out of its track on the English panel.
     */
    const renderToggle = (key: string, label: string, hint: string): ReactNode => {
        const on = data[key] === '1';

        return (
            <div key={key} className="flex items-center justify-between gap-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                <div className="min-w-0">
                    <span className="text-sm font-medium text-neutral-700 dark:text-neutral-200">{label}</span>
                    <p className="text-xs text-neutral-400">{hint}</p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={on}
                    aria-label={label}
                    onClick={() => setData(key, on ? '0' : '1')}
                    className={`relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors ${
                        on ? 'bg-brand-teal' : 'bg-neutral-300 dark:bg-neutral-600'
                    }`}
                >
                    <span
                        aria-hidden
                        className="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                        style={{
                            insetInlineStart: '0.125rem',
                            transform: on ? `translateX(${rtl ? '-1.25rem' : '1.25rem'})` : 'translateX(0)',
                        }}
                    />
                </button>
            </div>
        );
    };

    return (
        <AdminLayout title={t('admin.settings.title')}>
            <Head title={t('admin.settings.title')} />

            <PageHeader
                actions={
                    <>
                        <span className="text-xs text-neutral-400">{t('admin.settings.saveHint')}</span>
                        <Button type="submit" form="settings-form" variant="primary" disabled={processing || !isDirty}>
                            {t('admin.settings.save')}
                        </Button>
                    </>
                }
            />

            {undoMeta && (
                <div className="mb-4">
                    <UndoButton section="settings" undoMeta={undoMeta} />
                </div>
            )}

            <form id="settings-form" onSubmit={submit} className="space-y-6">
                <div className="gap-6 xl:columns-2">
                    {SECTIONS.map((s) => (
                        <section key={s.key} className={CARD}>
                            {sectionHeader(s.icon, s.key)}
                            <div className="grid gap-4 sm:grid-cols-2">{s.fields.map(renderField)}</div>
                        </section>
                    ))}

                    {/* Which payment methods checkout offers. Switching one off hides it
                        from checkout AND makes it invalid server-side; it never affects an
                        order already placed with that method, which stays payable. */}
                    <section id="payment-methods" className={CARD}>
                        {sectionHeader(CreditCard, 'payments')}
                        <div className="space-y-3">
                            {PAYMENT_METHODS.map((m) =>
                                renderToggle(
                                    `payment_${m}_enabled`,
                                    t(`admin.settings.paymentMethods.${m}.label`),
                                    t(`admin.settings.paymentMethods.${m}.hint`),
                                ),
                            )}
                        </div>
                        {!PAYMENT_KEYS.some((k) => data[k] === '1') && (
                            <p className="mt-3 text-xs font-medium text-red-600 dark:text-red-400">
                                {t('admin.settings.paymentMethods.noneWarning')}
                            </p>
                        )}
                    </section>

                    {/* Admin-panel preferences (not storefront) — holds the help beam toggle. */}
                    <section id="help-pulse" className={CARD}>
                        {sectionHeader(SlidersHorizontal, 'preferences')}
                        {renderToggle('admin_help_pulse', t('admin.settings.helpPulse.label'), t('admin.settings.helpPulse.hint'))}
                    </section>

                    {canReset && (
                        <section className="mb-6 break-inside-avoid rounded-xl border border-red-300 bg-red-50 p-5 shadow-sm sm:p-6 dark:border-red-900/60 dark:bg-red-950/30">
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
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') e.preventDefault();
                                                    }}
                                                    autoFocus
                                                    className="mt-1 w-full max-w-xs rounded-lg border border-red-300 px-3 py-2 text-sm dark:border-red-900 dark:bg-neutral-950"
                                                />
                                            </label>
                                            <div className="flex gap-2">
                                                <Button variant="danger" disabled={confirmText !== CONFIRM_WORD} onClick={doReset}>
                                                    {t('admin.settings.reset.confirm')}
                                                </Button>
                                                <Button
                                                    variant="secondary"
                                                    onClick={() => {
                                                        setConfirming(false);
                                                        setConfirmText('');
                                                    }}
                                                >
                                                    {t('admin.settings.reset.cancel')}
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </section>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
