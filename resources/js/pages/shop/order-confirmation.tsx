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

export default function OrderConfirmation({ order, bank }: { order: Order; bank: Bank | null }) {
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

                <Link href="/" className="mt-6 inline-block text-[#2f4f4f] underline">
                    {t('order.backToStore')}
                </Link>
            </div>
        </StoreLayout>
    );
}
