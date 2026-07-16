import { Head, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import UndoButton, { type UndoMeta } from '@/components/admin/undo-button';
import { useHighlightFields } from '@/hooks/use-highlight-fields';
import { useAdminT } from '@/i18n/use-admin-t';

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

export default function SettingsIndex({ settings, defaults = {}, undoMeta = null }: { settings: Settings; defaults?: Record<string, string>; undoMeta?: UndoMeta | null }) {
    const { t } = useAdminT();
    useHighlightFields();
    const flash = (usePage().props as { flash?: { success?: string | null } }).flash;
    const { data, setData, put, processing, errors } = useForm(
        Object.fromEntries(FIELDS.map((f) => [f.key, settings[f.key] ?? ''])) as Record<string, string>,
    );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/admin/settings', { preserveScroll: true });
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
        </AdminLayout>
    );
}
