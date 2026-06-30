import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import StoreLayout from '@/layouts/store-layout';

interface Profile {
    name: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    phone_verified: boolean;
    whatsapp_opt_in: boolean;
}

export default function AccountProfile({ profile }: { profile: Profile }) {
    const flash = (usePage().props as { flash?: { success?: string | null } }).flash;

    const { data, setData, patch, processing, errors } = useForm({
        name: profile.name ?? '',
        email: profile.email ?? '',
        city: profile.city ?? '',
        whatsapp_opt_in: profile.whatsapp_opt_in,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        patch('/account/profile', { preserveScroll: true });
    };

    return (
        <StoreLayout>
            <Head title="تعديل البيانات" />

            <div className="mx-auto max-w-lg">
                <Link href="/account" className="text-sm text-gray-500 underline">← حسابي</Link>
                <h1 className="mb-6 mt-1 text-2xl font-bold">تعديل البيانات</h1>

                {flash?.success && (
                    <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4 rounded-lg border border-gray-200 bg-white p-5">
                    <label className="block">
                        <span className="text-sm text-gray-600">رقم الجوال</span>
                        <input
                            value={profile.phone ?? ''}
                            dir="ltr"
                            disabled
                            className="mt-1 w-full rounded border border-gray-200 bg-gray-50 px-3 py-2 text-start text-gray-500"
                        />
                        <span className="text-xs text-gray-400">
                            {profile.phone_verified ? 'موثّق عبر واتساب' : 'غير موثّق'}
                        </span>
                    </label>

                    <label className="block">
                        <span className="text-sm text-gray-600">الاسم</span>
                        <input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                        />
                        {errors.name && <span className="text-xs text-red-500">{errors.name}</span>}
                    </label>

                    <label className="block">
                        <span className="text-sm text-gray-600">البريد الإلكتروني</span>
                        <input
                            type="email"
                            dir="ltr"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-start"
                        />
                        {errors.email && <span className="text-xs text-red-500">{errors.email}</span>}
                    </label>

                    <label className="block">
                        <span className="text-sm text-gray-600">المدينة</span>
                        <input
                            value={data.city}
                            onChange={(e) => setData('city', e.target.value)}
                            className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                        />
                    </label>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.whatsapp_opt_in}
                            onChange={(e) => setData('whatsapp_opt_in', e.target.checked)}
                        />
                        أرغب باستقبال العروض والتحديثات عبر واتساب
                    </label>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-lg bg-[#2f4f4f] px-6 py-3 font-semibold text-white transition hover:bg-[#264141] disabled:opacity-60"
                    >
                        حفظ
                    </button>
                </form>
            </div>
        </StoreLayout>
    );
}
