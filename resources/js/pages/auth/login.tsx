import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { t } = useTranslation();

    return (
        <AuthLayout title={t('auth.login.title')} description={t('auth.login.subtitle')}>
            <Head title={t('auth.login.title')} />

            {/* Uncontrolled Inertia <Form>: submits the actual DOM field values via
                FormData, so browser / password-manager autofill can't desync the
                submitted credentials (the cause of "first login attempt fails on a
                fresh browser, works on retry"). */}
            <Form action={route('login')} method="post" resetOnError={['password']} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">{t('auth.email')}</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder={t('auth.emailPlaceholder')}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">{t('auth.password')}</Label>
                                    {canResetPassword && (
                                        <TextLink href={route('password.request')} className="ms-auto text-sm" tabIndex={5}>
                                            {t('auth.forgotPassword')}
                                        </TextLink>
                                    )}
                                </div>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder={t('auth.passwordPlaceholder')}
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

                        <div className="text-center text-sm text-neutral-500">
                            {t('auth.login.noAccount')}{' '}
                            <TextLink href={route('register')} tabIndex={5}>
                                {t('auth.login.signUp')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

            {status && <div className="mt-4 text-center text-sm font-medium text-green-600">{status}</div>}
        </AuthLayout>
    );
}
