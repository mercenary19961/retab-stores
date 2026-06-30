import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import StoreLayout from '@/layouts/store-layout';

export default function WhatsAppLogin() {
    const [step, setStep] = useState<'phone' | 'code'>('phone');
    const { data, setData, post, processing, errors, reset } = useForm({ phone: '', code: '' });

    const sendCode = (e: FormEvent) => {
        e.preventDefault();
        post('/login/whatsapp/send', {
            preserveScroll: true,
            onSuccess: () => setStep('code'),
        });
    };

    const verify = (e: FormEvent) => {
        e.preventDefault();
        post('/login/whatsapp/verify', { preserveScroll: true });
    };

    return (
        <StoreLayout>
            <Head title="الدخول عبر واتساب" />

            <div className="mx-auto max-w-md">
                <h1 className="mb-2 text-2xl font-bold">الدخول عبر واتساب</h1>

                {step === 'phone' ? (
                    <>
                        <p className="mb-6 text-sm text-gray-600">
                            أدخل رقم جوالك وسنرسل لك رمز تحقق عبر واتساب.
                        </p>
                        <form onSubmit={sendCode} className="space-y-4">
                            <label className="block">
                                <span className="text-sm text-gray-600">رقم الجوال</span>
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
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-lg bg-[#25D366] px-6 py-3 font-semibold text-white transition hover:bg-[#1da851] disabled:opacity-60"
                            >
                                إرسال الرمز عبر واتساب
                            </button>
                        </form>
                    </>
                ) : (
                    <>
                        <p className="mb-6 text-sm text-gray-600">
                            أرسلنا رمزاً مكوناً من 6 أرقام إلى <span dir="ltr" className="font-mono">{data.phone}</span>.
                        </p>
                        <form onSubmit={verify} className="space-y-4">
                            <label className="block">
                                <span className="text-sm text-gray-600">رمز التحقق</span>
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
                                تأكيد الدخول
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    reset('code');
                                    setStep('phone');
                                }}
                                className="w-full text-sm text-gray-500 underline"
                            >
                                تغيير الرقم
                            </button>
                        </form>
                    </>
                )}

                <div className="mt-6 text-center text-sm text-gray-500">
                    <Link href="/login" className="underline">الدخول بالبريد الإلكتروني بدلاً من ذلك</Link>
                </div>
            </div>
        </StoreLayout>
    );
}
