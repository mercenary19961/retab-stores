import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import WhatsAppLoginLink from '@/components/auth/whatsapp-login-link';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

export default function Register() {
    const { t } = useTranslation();

    return (
        <AuthLayout title={t('auth.register.title')} description={t('auth.register.subtitle')}>
            <Head title={t('auth.register.title')} />
            {/* `noValidate`, and deliberately NO `required` on any field below. The
                browser's native validation intercepts the submit before Inertia sees
                it, so every `InputError` on this form was unreachable for an empty
                field and the customer got a browser tooltip instead — in the BROWSER's
                language, which on an Arabic-first store is routinely the wrong one.
                Server-validated only, matching contact, profile, checkout and returns. */}
            <Form
                action={route('register')}
                method="post"
                resetOnError={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
                noValidate
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('auth.name')}</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    placeholder={t('auth.namePlaceholder')}
                                />
                                <InputError message={errors.name} className="mt-2" />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">{t('auth.email')}</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    tabIndex={2}
                                    autoComplete="email"
                                    placeholder={t('auth.emailPlaceholder')}
                                />
                                <InputError message={errors.email} />
                            </div>

                            {/* Both password fields use the shared PasswordInput so the
                                show/hide toggle matches the login screen. Each keeps its
                                own toggle state: confirming a password you cannot see is
                                the point of the second field, so revealing one must not
                                reveal the other. */}
                            <div className="grid gap-2">
                                <Label htmlFor="password">{t('auth.password')}</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    placeholder={t('auth.passwordPlaceholder')}
                                    showLabel={t('auth.showPassword')}
                                    hideLabel={t('auth.hidePassword')}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">{t('auth.confirmPassword')}</Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    placeholder={t('auth.confirmPasswordPlaceholder')}
                                    showLabel={t('auth.showPassword')}
                                    hideLabel={t('auth.hidePassword')}
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>

                            <Button type="submit" className="mt-2 w-full" tabIndex={5} disabled={processing}>
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                {t('auth.register.submit')}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-neutral-500">
                            {t('auth.register.haveAccount')}{' '}
                            <TextLink href={route('login')} tabIndex={6}>
                                {t('auth.register.signIn')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

            {/* Renders nothing while WhatsApp cannot deliver a code. */}
            <div className="mt-4">
                <WhatsAppLoginLink />
            </div>
        </AuthLayout>
    );
}
