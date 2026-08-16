import Button from '@/components/admin/button';
import PasswordInput from '@/components/password-input';
import { useAdminT } from '@/i18n/use-admin-t';
import { useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { type FormEvent, useRef } from 'react';

/**
 * Own-account password change, styled for the admin panel.
 *
 * Posts to the SHARED `password.update` route rather than an admin-specific
 * endpoint, so there is exactly one place a password changes (it validates
 * `current_password`, applies Password::defaults() and returns back(), which
 * lands the caller wherever it submitted from). The success flash surfaces
 * through the admin toast layer.
 *
 * Only ever renders for the signed-in user — an admin cannot set someone else's
 * password here, because `current_password` is by definition theirs alone.
 */
export default function ChangePasswordForm({ compact = false }: { compact?: boolean }) {
    const { t } = useAdminT();
    const currentRef = useRef<HTMLInputElement>(null);
    const newRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onError: (errors) => {
                // Clear and focus whichever field was rejected, so a retry starts
                // from an empty box instead of the stale wrong value.
                if (errors.current_password) {
                    form.reset('current_password');
                    currentRef.current?.focus();
                } else if (errors.password) {
                    form.reset('password', 'password_confirmation');
                    newRef.current?.focus();
                }
            },
        });
    };

    const field = (
        name: 'current_password' | 'password' | 'password_confirmation',
        label: string,
        autoComplete: string,
        ref?: React.RefObject<HTMLInputElement | null>,
    ) => (
        <label className="block">
            <span className="text-xs text-neutral-400">{label}</span>
            <PasswordInput
                ref={ref}
                dir="ltr"
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                autoComplete={autoComplete}
                showLabel={t('admin.account.showPassword')}
                hideLabel={t('admin.account.hidePassword')}
                className="mt-1 border-neutral-700 bg-neutral-950 text-sm"
            />
            {form.errors[name] && <span className="text-xs text-red-400">{form.errors[name]}</span>}
        </label>
    );

    return (
        <form onSubmit={submit} className={compact ? 'space-y-4' : 'space-y-4 px-5 py-4'}>
            {!compact && (
                <div className="flex items-center gap-2">
                    <KeyRound className="text-brand-gold h-4 w-4" />
                    <h3 className="font-medium text-neutral-200">{t('admin.account.changePassword')}</h3>
                </div>
            )}
            <p className="text-xs text-neutral-500">{t('admin.account.passwordHint')}</p>

            <div className="grid gap-3 sm:grid-cols-3">
                {field('current_password', t('admin.account.currentPassword'), 'current-password', currentRef)}
                {field('password', t('admin.account.newPassword'), 'new-password', newRef)}
                {field('password_confirmation', t('admin.account.confirmPassword'), 'new-password')}
            </div>

            <Button type="submit" variant="primary" disabled={form.processing || !form.isDirty}>
                {t('admin.account.savePassword')}
            </Button>
        </form>
    );
}
