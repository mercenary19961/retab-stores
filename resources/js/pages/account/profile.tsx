import StoreLayout from '@/layouts/store-layout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { BadgeCheck, ChevronLeft, ShieldAlert } from 'lucide-react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

interface Profile {
    name: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    phone_verified: boolean;
    whatsapp_opt_in: boolean;
}

/** Shared field shell: label, control, and an error slot that reserves no space. */
function Field({ label, hint, error, children }: { label: string; hint?: string; error?: string; children: React.ReactNode }) {
    return (
        <label className="block">
            <span className="text-brand-teal text-sm font-bold">{label}</span>
            {children}
            {hint && !error && <span className="mt-1 block text-xs text-neutral-500">{hint}</span>}
            {error && <span className="mt-1 block text-xs font-medium text-red-600">{error}</span>}
        </label>
    );
}

const inputBase =
    'mt-1.5 w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-sm outline-none transition-colors focus:border-brand-teal focus:ring-1 focus:ring-brand-teal';

export default function AccountProfile({ profile }: { profile: Profile }) {
    const { t } = useTranslation();
    const flash = (usePage().props as { flash?: { success?: string | null } }).flash;

    const { data, setData, patch, processing, errors, isDirty } = useForm({
        name: profile.name ?? '',
        email: profile.email ?? '',
        city: profile.city ?? '',
        whatsapp_opt_in: profile.whatsapp_opt_in,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        patch('/account/profile', { preserveScroll: true });
    };

    // A WhatsApp-only signup arrives with nothing but a phone, so the page's job is
    // as much "finish setting up" as "edit". Nudge only while something is missing.
    const missing = [!profile.name, !profile.email, !profile.city].filter(Boolean).length;

    return (
        <StoreLayout>
            <Head title={t('profile.title')} />

            <div className="mx-auto max-w-xl">
                <Link href="/account" className="text-brand-gold hover:text-brand-teal inline-flex items-center gap-1 text-sm transition-colors">
                    {/* Physical chevron mirrored by direction: "back" follows the reader. */}
                    <ChevronLeft className="size-4 rtl:-scale-x-100" />
                    {t('profile.backPlain')}
                </Link>

                <h1 className="font-heading text-brand-teal mt-2 text-2xl font-black sm:text-3xl">{t('profile.title')}</h1>
                <p className="mt-1 text-sm text-neutral-600">{t('profile.subtitle')}</p>

                {flash?.success && (
                    <div className="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">
                        {flash.success}
                    </div>
                )}

                {missing > 0 && (
                    <div className="border-brand-gold/40 bg-brand-cream/60 text-brand-teal mt-5 flex items-start gap-2 rounded-xl border px-4 py-3 text-sm">
                        <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                        <span>{t('profile.completePrompt', { n: missing })}</span>
                    </div>
                )}

                <form onSubmit={submit} noValidate className="border-brand-gold/20 mt-5 space-y-5 rounded-3xl border bg-white p-5 shadow-sm sm:p-7">
                    {/* Phone is the account identity under the OTP model, so it is shown
                        but not editable here — changing it would move the login itself. */}
                    <Field label={t('profile.phone')}>
                        <input
                            value={profile.phone ?? '—'}
                            dir="ltr"
                            disabled
                            className={`${inputBase} cursor-not-allowed border-neutral-200 bg-neutral-50 text-start text-neutral-500`}
                        />
                        <span
                            className={`mt-1.5 inline-flex items-center gap-1 text-xs font-medium ${
                                profile.phone_verified ? 'text-green-700' : 'text-amber-700'
                            }`}
                        >
                            <BadgeCheck className="size-3.5" />
                            {profile.phone_verified ? t('profile.verified') : t('profile.unverified')}
                        </span>
                    </Field>

                    <Field label={t('profile.name')} error={errors.name}>
                        <input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoComplete="name"
                            aria-invalid={Boolean(errors.name)}
                            className={`${inputBase} ${errors.name ? 'border-red-400' : ''}`}
                        />
                    </Field>

                    <Field label={t('profile.email')} hint={t('profile.emailHint')} error={errors.email}>
                        <input
                            type="email"
                            dir="ltr"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="email"
                            aria-invalid={Boolean(errors.email)}
                            className={`${inputBase} text-start ${errors.email ? 'border-red-400' : ''}`}
                        />
                    </Field>

                    <Field label={t('profile.city')} error={errors.city}>
                        <input
                            value={data.city}
                            onChange={(e) => setData('city', e.target.value)}
                            autoComplete="address-level2"
                            className={`${inputBase} ${errors.city ? 'border-red-400' : ''}`}
                        />
                    </Field>

                    <label className="border-brand-gold/20 bg-brand-cream/40 flex cursor-pointer items-start gap-3 rounded-xl border p-4 text-sm">
                        <input
                            type="checkbox"
                            checked={data.whatsapp_opt_in}
                            onChange={(e) => setData('whatsapp_opt_in', e.target.checked)}
                            className="accent-brand-teal mt-0.5 size-4 shrink-0"
                        />
                        <span className="text-neutral-700">{t('profile.whatsappOptIn')}</span>
                    </label>

                    <button
                        type="submit"
                        // Gated on `isDirty` so the button reflects whether pressing it
                        // would do anything, rather than firing a no-op round trip.
                        disabled={processing || !isDirty}
                        className="bg-brand-teal w-full rounded-full px-6 py-3 text-sm font-bold text-white transition-colors hover:bg-[#163e42] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {processing ? t('common.saving') : t('common.save')}
                    </button>
                </form>
            </div>
        </StoreLayout>
    );
}
