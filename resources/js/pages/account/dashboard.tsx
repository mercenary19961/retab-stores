import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import StoreLayout from '@/layouts/store-layout';

interface OrderRow {
    order_number: string;
    status: string;
    payment_status: string;
    total: number;
    created_at: string | null;
}

interface Reward {
    code: string;
    value: number;
}

interface Loyalty {
    confirmed_purchases: number;
    milestone: number;
    progress: number;
    remaining: number;
    reward_percent: number;
    rewards: Reward[];
}

interface Profile {
    name: string | null;
    phone: string | null;
}

export default function AccountDashboard({
    orders,
    loyalty,
}: {
    profile: Profile;
    orders: OrderRow[];
    loyalty: Loyalty;
}) {
    const { t } = useTranslation();
    const currency = t('common.currency');

    return (
        <StoreLayout>
            <Head title={t('account.title')} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-bold">{t('account.title')}</h1>
                <button type="button" onClick={() => router.post('/logout')} className="text-sm text-gray-500 underline">
                    {t('common.logout')}
                </button>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Loyalty */}
                <section className="rounded-lg border border-gray-200 bg-white p-5 lg:col-span-1">
                    <h2 className="mb-1 font-bold">{t('account.loyaltyHeading')}</h2>
                    <p className="text-sm text-gray-600">
                        {t('account.loyaltyRule', { milestone: loyalty.milestone, percent: loyalty.reward_percent })}
                    </p>

                    <div className="mt-4">
                        <div className="flex gap-1.5">
                            {Array.from({ length: loyalty.milestone }).map((_, i) => (
                                <div
                                    key={i}
                                    className={`h-2.5 flex-1 rounded-full ${i < loyalty.progress ? 'bg-[#2f4f4f]' : 'bg-gray-200'}`}
                                />
                            ))}
                        </div>
                        <p className="mt-2 text-sm text-gray-600">
                            {t('account.confirmedOrders', { n: loyalty.confirmed_purchases })}
                            {loyalty.remaining > 0 && t('account.remainingForReward', { n: loyalty.remaining })}
                        </p>
                    </div>

                    {loyalty.rewards.length > 0 && (
                        <div className="mt-4 rounded-lg bg-green-50 p-3">
                            <p className="mb-1 text-sm font-semibold text-green-800">{t('account.rewardsHeading')}</p>
                            {loyalty.rewards.map((r) => (
                                <div key={r.code} className="flex justify-between text-sm text-green-800">
                                    <span className="font-mono">{r.code}</span>
                                    <span>{r.value}%</span>
                                </div>
                            ))}
                        </div>
                    )}

                    <div className="mt-4 flex flex-col gap-1 text-sm">
                        <Link href="/account/profile" className="text-[#2f4f4f] underline">{t('account.editProfile')}</Link>
                        <Link href="/wishlist" className="text-[#2f4f4f] underline">{t('account.wishlist')}</Link>
                    </div>
                </section>

                {/* Orders */}
                <section className="rounded-lg border border-gray-200 bg-white p-5 lg:col-span-2">
                    <h2 className="mb-3 font-bold">{t('account.myOrders')}</h2>
                    {orders.length === 0 ? (
                        <p className="text-sm text-gray-400">{t('account.noOrders')}</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="text-start text-gray-500">
                                <tr>
                                    <th className="py-2 text-start font-medium">{t('account.colOrderNumber')}</th>
                                    <th className="py-2 text-start font-medium">{t('account.colStatus')}</th>
                                    <th className="py-2 text-start font-medium">{t('account.colTotal')}</th>
                                    <th className="py-2 text-start font-medium">{t('account.colDate')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.map((o) => (
                                    <tr key={o.order_number} className="border-t border-gray-100">
                                        <td className="py-2">
                                            <Link href={`/orders/${o.order_number}`} className="font-mono text-[#2f4f4f] underline">
                                                {o.order_number}
                                            </Link>
                                        </td>
                                        <td className="py-2">{t(`status.${o.status}`, o.status)}</td>
                                        <td className="py-2">{o.total} {currency}</td>
                                        <td className="py-2 text-gray-500">{o.created_at ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            </div>
        </StoreLayout>
    );
}
