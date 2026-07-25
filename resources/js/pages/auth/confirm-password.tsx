import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

export default function ConfirmPassword() {
    const { t } = useTranslation();

    return (
        <AuthLayout title={t('auth.confirm.title')} description={t('auth.confirm.subtitle')}>
            <Head title={t('auth.confirm.title')} />

            <Form action={route('password.confirm')} method="post" resetOnError={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">{t('auth.password')}</Label>
                            <Input id="password" name="password" type="password" placeholder={t('auth.passwordPlaceholder')} autoComplete="current-password" autoFocus />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button className="w-full" disabled={processing}>
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                {t('auth.confirm.submit')}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
