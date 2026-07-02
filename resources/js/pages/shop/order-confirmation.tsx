import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import StoreLayout from '@/layouts/store-layout';

interface Order {
    order_number: string;
    status: string;
    payment_status: string;
    payment_method: string | null;
    total: number;
}

interface Bank {
    bank_name: string;
    beneficiary: string;
    account: string;
    iban: string;
}

export default function OrderConfirmation({
    order,
    bank,
    canReturn,
    orderReturn,
}: {
    order: Order;
    bank: Bank | null;
    canReturn?: boolean;
    orderReturn?: { status: string } | null;
}) {
    const { t } = useTranslation();
    const currency = t('common.currency');

    return (
        <StoreLayout>
            <Head title={t('order.headTitle', { number: order.order_number })} />

            <div className="mx-auto max-w-xl text-center">
                <div className="text-5xl">✅</div>
                <h1 className="mt-3 text-2xl font-bold">{t('order.received')}</h1>
                <p className="mt-2 text-gray-600">
                    {t('order.orderNumber')}: <span className="font-mono font-semibold">{order.order_number}</span>
                </p>
                <p className="mt-1 text-gray-600">{t('order.total')}: {order.total} {currency}</p>

                {bank ? (
                    <div className="mt-6 rounded-lg border border-gray-200 bg-white p-5 text-start">
                        <h2 className="mb-2 font-bold">{t('order.bankHeading')}</h2>
                        <p className="text-sm text-gray-600">{t('order.bankInstructions')}</p>
                        <dl className="mt-3 space-y-1 text-sm">
                            <div className="flex justify-between"><dt className="text-gray-500">{t('order.bankName')}</dt><dd>{bank.bank_name}</dd></div>
                            <div className="flex justify-between"><dt className="text-gray-500">{t('order.beneficiary')}</dt><dd>{bank.beneficiary}</dd></div>
                            <div className="flex justify-between"><dt className="text-gray-500">{t('order.account')}</dt><dd className="font-mono">{bank.account}</dd></div>
                            <div className="flex justify-between"><dt className="text-gray-500">{t('order.iban')}</dt><dd className="font-mono">{bank.iban}</dd></div>
                        </dl>
                    </div>
                ) : (
                    <p className="mt-4 text-gray-600">{t('order.noBank')}</p>
                )}

                {orderReturn && (
                    <p className="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        {t('returns.statusLabel')}: <b>{t(`returns.status.${orderReturn.status}`, orderReturn.status)}</b>
                    </p>
                )}

                {canReturn && !orderReturn && (
                    <Link
                        href={`/orders/${order.order_number}/return`}
                        className="mt-4 inline-block rounded-lg border border-[#2f4f4f] px-5 py-2 text-sm font-semibold text-[#2f4f4f] transition hover:bg-[#2f4f4f] hover:text-white"
                    >
                        {t('returns.requestButton', { days: 3 })}
                    </Link>
                )}

                <Link href="/" className="mt-6 block text-[#2f4f4f] underline">
                    {t('order.backToStore')}
                </Link>
            </div>
        </StoreLayout>
    );
}
