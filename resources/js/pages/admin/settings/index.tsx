import { Head, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import UndoButton, { type UndoMeta } from '@/components/admin/undo-button';
import { useHighlightFields } from '@/hooks/use-highlight-fields';

type Settings = Record<string, string | null>;

type Field = { key: string; label: string; hint?: string; type?: string; dir?: string; group?: string };

const FIELDS: Field[] = [
    { key: 'shipping_flat_fee', label: 'Flat shipping fee (SAR)', hint: 'One fixed price for all GCC destinations.', type: 'number' },
    { key: 'legal_name', label: 'Legal name', dir: 'auto' },
    { key: 'bank_name', label: 'Bank name', dir: 'auto' },
    { key: 'bank_beneficiary', label: 'Bank beneficiary', dir: 'auto' },
    { key: 'bank_account', label: 'Bank account number' },
    { key: 'bank_iban', label: 'IBAN', hint: 'Shown to bank-transfer customers on the order page.' },
    // Footer / contact block — shown site-wide. Blank = fall back to the default (placeholder).
    { key: 'contact_phone', label: 'Contact phone', dir: 'ltr', group: 'Footer & contact' },
    { key: 'contact_email', label: 'Contact email', type: 'email', dir: 'ltr' },
    { key: 'commercial_registration', label: 'Commercial registration no.', dir: 'ltr' },
    { key: 'vat_number', label: 'VAT number', dir: 'ltr' },
    { key: 'social_snapchat', label: 'Snapchat URL', type: 'url', dir: 'ltr' },
    { key: 'social_facebook', label: 'Facebook URL', type: 'url', dir: 'ltr' },
    { key: 'social_instagram', label: 'Instagram URL', type: 'url', dir: 'ltr' },
    { key: 'social_x', label: 'X (Twitter) URL', type: 'url', dir: 'ltr' },
    { key: 'social_linkedin', label: 'LinkedIn URL', type: 'url', dir: 'ltr' },
];

export default function SettingsIndex({ settings, defaults = {}, undoMeta = null }: { settings: Settings; defaults?: Record<string, string>; undoMeta?: UndoMeta | null }) {
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
        <AdminLayout title="Settings">
            <Head title="Settings" />

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
                {FIELDS.map((f) => (
                    <div key={f.key}>
                        {f.group && (
                            <h2 className="mb-1 mt-4 border-t border-neutral-200 pt-4 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:border-neutral-800">
                                {f.group}
                            </h2>
                        )}
                        <label className="block" id={`field-${f.key}`}>
                            <span className="text-sm text-neutral-500">{f.label}</span>
                            <input
                                type={f.type ?? 'text'}
                                step={f.type === 'number' ? '0.01' : undefined}
                                dir={f.dir}
                                value={data[f.key]}
                                placeholder={defaults[f.key]}
                                onChange={(e) => setData(f.key, e.target.value)}
                                className="mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-950"
                            />
                            {f.hint && <span className="text-xs text-neutral-400">{f.hint}</span>}
                            {errors[f.key] && <span className="block text-xs text-red-500">{errors[f.key]}</span>}
                        </label>
                    </div>
                ))}

                <Button type="submit" variant="primary" disabled={processing}>
                    Save settings
                </Button>
            </form>
        </AdminLayout>
    );
}
