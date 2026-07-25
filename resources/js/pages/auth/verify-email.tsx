import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation();

    return (
        <AuthLayout title={t('auth.verify.title')} description={t('auth.verify.subtitle')}>
            <Head title={t('auth.verify.title')} />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">{t('auth.verify.sent')}</div>
            )}

            <Form action={route('verification.send')} method="post" className="text-center">
                {({ processing }) => (
                    <Button disabled={processing} variant="secondary">
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {t('auth.verify.resend')}
                    </Button>
                )}
            </Form>

            <TextLink href={route('logout')} method="post" className="mx-auto mt-6 block text-center text-sm">
                {t('auth.verify.logout')}
            </TextLink>
        </AuthLayout>
    );
}
