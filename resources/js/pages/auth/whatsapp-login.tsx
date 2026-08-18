import { Turnstile, type TurnstileHandle } from '@/components/turnstile';
import StoreLayout from '@/layouts/store-layout';
import { type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

/**
 * ⚠️ The index signature is what lets `errors.whatsapp` typecheck. Inertia types the
 * error bag against the FORM's keys, but a form-level error is deliberately keyed to
 * something that is NOT a field (see OtpAuthController) so it cannot mark a valid
 * phone number as invalid. Same shape the contact form uses for `errors.turnstile`.
 */
interface FormData {
    phone: string;
    code: string;
    'cf-turnstile-response': string;
    [key: string]: string;
}

export default function WhatsAppLogin() {
    const { t } = useTranslation();
    const { whatsappAuth } = usePage<SharedData>().props;
    const [step, setStep] = useState<'phone' | 'code'>('phone');
    const turnstileRef = useRef<TurnstileHandle>(null);
    const { data, setData, post, processing, errors, reset } = useForm<FormData>({
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
                <h1 className="mb-2 text-2xl font-bold">{whatsappAuth ? t('login.title') : t('login.unavailableTitle')}</h1>

                {/* 🔴 The whole reason this branch exists: with WhatsApp unconfigured
                    the transport is the LOG driver, which reports every send as a
                    success. So the form would happily accept a number, advance to the
                    code step, and leave the customer waiting for a code that had gone
                    to the server log — no error, no way forward, on the store's
                    primary sign-in method.

                    Rather than show a form that cannot work, send them to the door
                    that does. The server refuses the POST too (see OtpAuthController),
                    so this is presentation, not the guard. */}
                {!whatsappAuth ? (
                    <div className="mt-4 space-y-4">
                        <p className="text-sm text-gray-600">{t('login.unavailableBody')}</p>
                        <Link
                            href="/login"
                            className="bg-brand-teal block rounded-lg px-6 py-3 text-center font-semibold text-white transition hover:bg-[#163e42]"
                        >
                            {t('login.goToEmailLogin')}
                        </Link>
                        <Link
                            href="/register"
                            className="border-brand-gold/40 text-brand-teal hover:bg-brand-cream block rounded-lg border px-6 py-3 text-center font-semibold transition"
                        >
                            {t('login.createAccount')}
                        </Link>
                    </div>
                ) : step === 'phone' ? (
                    <>
                        <p className="mb-6 text-sm text-gray-600">{t('login.phoneInstructions')}</p>
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
                            {/* Form-level, not under the phone field: the channel is
                                down, so the number they typed is not the problem and
                                marking it red would send them off correcting a
                                perfectly good phone number. Only reachable if the
                                shared prop went stale between render and submit — the
                                page normally hides this form entirely. */}
                            {errors.whatsapp && (
                                <div role="alert" className="flex items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-2.5">
                                    <AlertCircle aria-hidden className="size-4 shrink-0 text-red-500" />
                                    <span className="text-sm text-red-700">{errors.whatsapp}</span>
                                </div>
                            )}
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
                            {t('login.codeInstructions')}{' '}
                            <span dir="ltr" className="font-mono">
                                {data.phone}
                            </span>
                            .
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

                {/* Suppressed in the unavailable state, where the two buttons above
                    already are the way out — a third link to the same place reads as
                    clutter. */}
                {whatsappAuth && (
                    <div className="mt-6 text-center text-sm text-gray-500">
                        <Link href="/login" className="underline">
                            {t('login.useEmail')}
                        </Link>
                    </div>
                )}
            </div>
        </StoreLayout>
    );
}
