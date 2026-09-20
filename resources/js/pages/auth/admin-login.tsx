import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

/**
 * Staff sign-in.
 *
 * 🔑 Deliberately the customer login MINUS everything: no Google, no WhatsApp,
 * no "create an account" link. Staff accounts exist only at /admin/users, so
 * every one of those would advertise a door that does not open for them.
 *
 * "Forgot password" stays, because it is the one self-service action a member of
 * staff genuinely has — the Staff page will not reset the owner's password.
 */
export default function AdminLogin() {
    const { t } = useTranslation();

    return (
        <AuthLayout title={t('auth.adminLogin.title')} description={t('auth.adminLogin.subtitle')}>
            <Head title={t('auth.adminLogin.title')} />

            {/* Uncontrolled <Form> + noValidate, matching the customer login: native
                validation would intercept the submit and the localized InputError
                messages could never render for an empty field. */}
            <Form action={route('admin.login.store')} method="post" resetOnError={['password']} className="flex flex-col gap-6" noValidate>
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('auth.email')}</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                placeholder={t('auth.emailPlaceholder')}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">{t('auth.password')}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder={t('auth.passwordPlaceholder')}
                                showLabel={t('auth.showPassword')}
                                hideLabel={t('auth.hidePassword')}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center gap-3">
                            <Checkbox id="remember" name="remember" tabIndex={3} />
                            <Label htmlFor="remember">{t('auth.remember')}</Label>
                        </div>

                        <Button type="submit" className="mt-2 w-full" tabIndex={4} disabled={processing}>
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            {t('auth.login.submit')}
                        </Button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
