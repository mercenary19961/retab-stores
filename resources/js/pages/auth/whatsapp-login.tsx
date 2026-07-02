import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Turnstile, type TurnstileHandle } from '@/components/turnstile';
import StoreLayout from '@/layouts/store-layout';

export default function WhatsAppLogin() {
    const { t } = useTranslation();
    const [step, setStep] = useState<'phone' | 'code'>('phone');
    const turnstileRef = useRef<TurnstileHandle>(null);
    const { data, setData, post, processing, errors, reset } = useForm({
        phone: '',
        code: '',
        'cf-turnstile-response': '',
    });

    const sendCode = (e: FormEvent) => {
        e.preventDefault();
        post('/login/whatsapp/send', {
            preserveScroll: true,
            onSuccess: () => setStep('code'),
            // Tokens are single-use — re-arm the widget after a rejected submit.
            onError: () => turnstileRef.current?.reset(),
        });
    };

    const verify = (e: FormEvent) => {
        e.preventDefault();
        post('/login/whatsapp/verify', { preserveScroll: true });
    };

    return (
        <StoreLayout>
            <Head title={t('login.title')} />

            <div className="mx-auto max-w-md">
                <h1 className="mb-2 text-2xl font-bold">{t('login.title')}</h1>

                {step === 'phone' ? (
                    <>
                        <p className="mb-6 text-sm text-gray-600">
                            {t('login.phoneInstructions')}
                        </p>
                        <form onSubmit={sendCode} className="space-y-4">
                            <label className="block">
                                <span className="text-sm text-gray-600">{t('login.phone')}</span>
                                <input
                                    type="tel"
                                    inputMode="tel"
                                    dir="ltr"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    placeholder="+9665XXXXXXXX"
                                    className="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-start"
                                />
                                {errors.phone && <span className="text-xs text-red-500">{errors.phone}</span>}
                            </label>
                            <Turnstile
                                ref={turnstileRef}
                                onVerify={(token) => setData('cf-turnstile-response', token)}
                                onExpire={() => setData('cf-turnstile-response', '')}
                            />
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-lg bg-[#25D366] px-6 py-3 font-semibold text-white transition hover:bg-[#1da851] disabled:opacity-60"
                            >
                                {t('login.sendCode')}
                            </button>
                        </form>
                    </>
                ) : (
                    <>
                        <p className="mb-6 text-sm text-gray-600">
                            {t('login.codeInstructions')} <span dir="ltr" className="font-mono">{data.phone}</span>.
                        </p>
                        <form onSubmit={verify} className="space-y-4">
                            <label className="block">
                                <span className="text-sm text-gray-600">{t('login.code')}</span>
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    dir="ltr"
                                    maxLength={6}
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value.replace(/\D/g, ''))}
                                    className="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-center text-lg tracking-[0.4em]"
                                />
                                {errors.code && <span className="text-xs text-red-500">{errors.code}</span>}
                            </label>
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-lg bg-[#2f4f4f] px-6 py-3 font-semibold text-white transition hover:bg-[#264141] disabled:opacity-60"
                            >
                                {t('login.confirm')}
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    reset('code');
                                    setStep('phone');
                                }}
                                className="w-full text-sm text-gray-500 underline"
                            >
                                {t('login.changeNumber')}
                            </button>
                        </form>
                    </>
                )}

                <div className="mt-6 text-center text-sm text-gray-500">
                    <Link href="/login" className="underline">{t('login.useEmail')}</Link>
                </div>
            </div>
        </StoreLayout>
    );
}
