import { Head, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';

type Settings = Record<string, string | null>;

const FIELDS: { key: string; label: string; hint?: string; type?: string; dir?: string }[] = [
    { key: 'shipping_flat_fee', label: 'Flat shipping fee (SAR)', hint: 'One fixed price for all GCC destinations.', type: 'number' },
    { key: 'legal_name', label: 'Legal name', dir: 'auto' },
    { key: 'bank_name', label: 'Bank name', dir: 'auto' },
    { key: 'bank_beneficiary', label: 'Bank beneficiary', dir: 'auto' },
    { key: 'bank_account', label: 'Bank account number' },
    { key: 'bank_iban', label: 'IBAN', hint: 'Shown to bank-transfer customers on the order page.' },
];

export default function SettingsIndex({ settings }: { settings: Settings }) {
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

            <form onSubmit={submit} className="max-w-lg space-y-4 rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                {FIELDS.map((f) => (
                    <label key={f.key} className="block">
                        <span className="text-sm text-neutral-500">{f.label}</span>
                        <input
                            type={f.type ?? 'text'}
                            step={f.type === 'number' ? '0.01' : undefined}
                            dir={f.dir}
                            value={data[f.key]}
                            onChange={(e) => setData(f.key, e.target.value)}
                            className="mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-950"
                        />
                        {f.hint && <span className="text-xs text-neutral-400">{f.hint}</span>}
                        {errors[f.key] && <span className="block text-xs text-red-500">{errors[f.key]}</span>}
                    </label>
                ))}

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-lg bg-neutral-900 px-5 py-2 text-sm font-semibold text-white hover:bg-neutral-700 disabled:opacity-60 dark:bg-white dark:text-neutral-900"
                >
                    Save settings
                </button>
            </form>
        </AdminLayout>
    );
}
