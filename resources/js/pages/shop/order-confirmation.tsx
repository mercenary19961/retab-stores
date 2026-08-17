import StoreLayout from '@/layouts/store-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

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
    canPay,
    canCancel,
    canReturn,
    orderReturn,
}: {
    order: Order;
    bank: Bank | null;
    canPay?: boolean;
    canCancel?: boolean;
    canReturn?: boolean;
    orderReturn?: { status: string } | null;
}) {
    const { t } = useTranslation();
    const currency = t('common.currency');
    const flash = (usePage().props as { flash?: { error?: string | null; success?: string | null } }).flash;
    const [paying, setPaying] = useState(false);
    // Two-step rather than a window.confirm(): cancelling releases the payment
    // and cannot be undone, and a native dialog can't be written in the
    // customer's own language or say what happens to their money.
    const [confirmingCancel, setConfirmingCancel] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const cancelled = order.status === 'cancelled';

    return (
        <StoreLayout>
            <Head title={t('order.headTitle', { number: order.order_number })} />

            <div className="mx-auto max-w-xl text-center">
                {/* ⚠️ A green tick over "order received" is a lie while the payment is
                    still outstanding, and it is probably why an abandoned checkout
                    never gets finished: the page reads as done. It was equally a lie
                    on a CANCELLED order, which kept the tick and the "received"
                    heading. State the truth in all three cases. */}
                <div className="text-5xl">{cancelled ? '🚫' : canPay ? '⏳' : '✅'}</div>
                <h1 className="mt-3 text-2xl font-bold">
                    {cancelled ? t('order.cancelledHeading') : canPay ? t('order.reserved') : t('order.received')}
                </h1>
                {cancelled && <p className="mt-2 text-sm text-neutral-500">{t('order.cancelledNote')}</p>}
                <p className="mt-2 text-gray-600">
                    {t('order.orderNumber')}: <span className="font-mono font-semibold">{order.order_number}</span>
                </p>
                <p className="mt-1 text-gray-600">
                    {t('order.total')}: {order.total} {currency}
                </p>

                {bank ? (
                    <div className="mt-6 rounded-lg border border-gray-200 bg-white p-5 text-start">
                        <h2 className="mb-2 font-bold">{t('order.bankHeading')}</h2>
                        <p className="text-sm text-gray-600">{t('order.bankInstructions')}</p>
                        <dl className="mt-3 space-y-1 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-gray-500">{t('order.bankName')}</dt>
                                <dd>{bank.bank_name}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">{t('order.beneficiary')}</dt>
                                <dd>{bank.beneficiary}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">{t('order.account')}</dt>
                                <dd className="font-mono">{bank.account}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">{t('order.iban')}</dt>
                                <dd className="font-mono">{bank.iban}</dd>
                            </div>
                        </dl>
                    </div>
                ) : canPay ? (
                    /* 🔴 The recovery path for an abandoned card/Tamara checkout. Until
                       this existed, closing the gateway tab left the order unpayable
                       and the customer with nowhere to go. Deliberately the loudest
                       thing on the page when it shows: the order is NOT paid yet, and
                       the success tick above otherwise implies it is. */
                    <div className="border-brand-gold/30 bg-brand-cream/50 mt-6 rounded-2xl border p-5">
                        <h2 className="text-brand-teal font-bold">{t('order.payHeading')}</h2>
                        <p className="mt-1 text-sm text-neutral-600">{t('order.payHint')}</p>
                        <button
                            type="button"
                            data-testid="resume-payment"
                            disabled={paying}
                            onClick={() =>
                                router.post(
                                    `/orders/${order.order_number}/pay`,
                                    {},
                                    { onStart: () => setPaying(true), onFinish: () => setPaying(false) },
                                )
                            }
                            className="bg-brand-teal mt-4 w-full rounded-full px-6 py-3 text-sm font-bold text-white transition-colors hover:bg-[#163e42] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {paying ? t('order.paying') : t('order.payButton')}
                        </button>
                    </div>
                ) : (
                    /* Suppressed on a cancelled order — there is no payment to
                       arrange, so the fallback line would only confuse. */
                    !cancelled && <p className="mt-4 text-gray-600">{t('order.noBank')}</p>
                )}

                {flash?.error && (
                    <p className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        {flash.error}
                    </p>
                )}

                {flash?.success && (
                    <p className="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
                        {flash.success}
                    </p>
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

                {/* Customer cancellation, allowed only until an admin confirms.
                    Deliberately the quietest control on the page — the brief asks
                    that cancelling isn't incentivised — so it's a plain text
                    button under everything else, not a bordered one competing
                    with Pay or Return. */}
                {canCancel && (
                    <div className="mt-8 border-t border-neutral-200 pt-5">
                        {confirmingCancel ? (
                            <div className="rounded-2xl border border-red-200 bg-red-50 p-4">
                                <p className="text-sm font-semibold text-red-800">{t('order.cancelConfirmTitle')}</p>
                                {/* Says what happens to their MONEY, which is the
                                    one thing a native confirm() could never do in
                                    the customer's own language. */}
                                <p className="mt-1 text-sm text-red-700">{t('order.cancelConfirmBody')}</p>
                                <div className="mt-4 flex flex-wrap justify-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setConfirmingCancel(false)}
                                        className="rounded-full border border-neutral-300 px-5 py-2 text-sm font-semibold text-neutral-700 transition hover:bg-white"
                                    >
                                        {t('order.cancelKeep')}
                                    </button>
                                    <button
                                        type="button"
                                        data-testid="cancel-order"
                                        disabled={cancelling}
                                        onClick={() =>
                                            router.post(
                                                `/orders/${order.order_number}/cancel`,
                                                {},
                                                {
                                                    preserveScroll: true,
                                                    onStart: () => setCancelling(true),
                                                    onFinish: () => {
                                                        setCancelling(false);
                                                        setConfirmingCancel(false);
                                                    },
                                                },
                                            )
                                        }
                                        className="rounded-full bg-red-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {cancelling ? t('order.cancelling') : t('order.cancelConfirmButton')}
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <button
                                type="button"
                                data-testid="cancel-order-open"
                                onClick={() => setConfirmingCancel(true)}
                                className="text-sm text-neutral-500 underline underline-offset-4 transition hover:text-red-700"
                            >
                                {t('order.cancelButton')}
                            </button>
                        )}
                    </div>
                )}

                <Link href="/" className="mt-6 block text-[#2f4f4f] underline">
                    {t('order.backToStore')}
                </Link>
            </div>
        </StoreLayout>
    );
}
