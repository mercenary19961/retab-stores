import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslation();

    return (
        <AuthLayout title={t('auth.forgot.title')} description={t('auth.forgot.subtitle')}>
            <Head title={t('auth.forgot.title')} />

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}

            <div className="space-y-6">
                <Form action={route('password.email')} method="post">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">{t('auth.email')}</Label>
                                <Input id="email" name="email" type="email" autoComplete="off" autoFocus placeholder={t('auth.emailPlaceholder')} />
                                <InputError message={errors.email} />
                            </div>

                            <div className="my-6">
                                <Button className="w-full" disabled={processing}>
                                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    {t('auth.forgot.submit')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="text-center text-sm text-neutral-500">
                    {t('auth.forgot.remembered')}{' '}
                    <TextLink href={route('login')}>{t('auth.forgot.loginLink')}</TextLink>
                </div>
            </div>
        </AuthLayout>
    );
}
