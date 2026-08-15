import Button from '@/components/admin/button';
import Modal from '@/components/admin/modal';
import { useAdminT } from '@/i18n/use-admin-t';
import { AlertCircle, Loader2, Truck } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface DeliveryOption {
    id: number;
    carrier: string;
    price: number;
    currency: string;
    estimated_delivery: string | null;
}

interface QuoteResponse {
    options: DeliveryOption[];
    error: string | null;
}

/** `null` = let the server pick the cheapest; a number = this exact carrier. */
type Choice = number | null;

/**
 * Carrier picker for the Ship action.
 *
 * Defaults to automatic (cheapest), which is almost always right: the customer
 * pays a flat rate regardless, so the price difference is entirely the store's
 * margin. The manual list exists for the cases the price can't express — a
 * faster carrier for an urgent order, or the one that actually serves a remote
 * district.
 *
 * Quotes are fetched when the dialog OPENS, never on page load: each one pushes
 * the order to OTO and burns a live rate lookup.
 */
export default function ShippingPicker({
    open,
    onClose,
    orderNumber,
    onConfirm,
    busy,
}: {
    open: boolean;
    onClose: () => void;
    orderNumber: string;
    onConfirm: (deliveryOptionId: Choice) => void;
    busy: boolean;
}) {
    const { t } = useAdminT();
    const [loading, setLoading] = useState(false);
    const [quote, setQuote] = useState<QuoteResponse | null>(null);
    const [choice, setChoice] = useState<Choice>(null);

    useEffect(() => {
        if (!open) return;

        // Reset per opening, so a retry after a failure doesn't show stale rates
        // and a previous manual pick can't silently carry over to a new dialog.
        setQuote(null);
        setChoice(null);
        setLoading(true);

        let cancelled = false;

        fetch(`/admin/orders/${orderNumber}/shipping-quotes`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
            .then((data: QuoteResponse) => {
                if (!cancelled) setQuote(data);
            })
            .catch(() => {
                if (!cancelled) setQuote({ options: [], error: t('admin.orders.shipping.quoteFailed') });
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        // The dialog can be closed mid-flight; without this the response would
        // set state on an unmounted-in-spirit dialog and repopulate it.
        return () => {
            cancelled = true;
        };
    }, [open, orderNumber, t]);

    const money = (option: DeliveryOption) => `${option.price.toFixed(2)} ${option.currency}`;

    return (
        <Modal open={open} onClose={onClose} title={t('admin.orders.shipping.title')} size="md">
            <p className="mb-4 text-sm text-neutral-500">{t('admin.orders.shipping.intro')}</p>

            {loading && (
                <div className="flex items-center gap-2 py-6 text-sm text-neutral-500">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    {t('admin.orders.shipping.loading')}
                </div>
            )}

            {!loading && quote?.error && (
                <div className="mb-4 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                    <span>
                        {/* Deliberately shows the provider's own words: "no options for
                            this destination" and "credentials rejected" need completely
                            different responses from the operator. */}
                        <span className="block font-medium">{t('admin.orders.shipping.quoteFailed')}</span>
                        <span className="block break-words opacity-80">{quote.error}</span>
                        <span className="mt-1 block">{t('admin.orders.shipping.autoStillWorks')}</span>
                    </span>
                </div>
            )}

            {!loading && (
                <div className="space-y-2">
                    <label
                        className={`flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors ${
                            choice === null
                                ? 'border-brand-gold bg-brand-gold/5'
                                : 'border-neutral-200 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/40'
                        }`}
                    >
                        <input type="radio" name="delivery-option" checked={choice === null} onChange={() => setChoice(null)} />
                        <span className="flex-1">
                            <span className="block text-sm font-medium">{t('admin.orders.shipping.automatic')}</span>
                            <span className="block text-xs text-neutral-500">{t('admin.orders.shipping.automaticHint')}</span>
                        </span>
                    </label>

                    {quote?.options.map((option, index) => (
                        <label
                            key={option.id}
                            className={`flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors ${
                                choice === option.id
                                    ? 'border-brand-gold bg-brand-gold/5'
                                    : 'border-neutral-200 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/40'
                            }`}
                        >
                            <input type="radio" name="delivery-option" checked={choice === option.id} onChange={() => setChoice(option.id)} />
                            <span className="flex-1">
                                <span className="flex items-center gap-2 text-sm font-medium">
                                    <Truck className="h-3.5 w-3.5 text-neutral-400" />
                                    {option.carrier}
                                    {/* Options arrive price-sorted, so the first is the one
                                        automatic mode would choose. Labelling it stops the
                                        operator wondering how the two relate. */}
                                    {index === 0 && (
                                        <span className="text-brand-gold rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] dark:bg-neutral-800">
                                            {t('admin.orders.shipping.cheapest')}
                                        </span>
                                    )}
                                </span>
                                <span className="block text-xs text-neutral-500">
                                    {option.estimated_delivery ?? t('admin.orders.shipping.noEta')}
                                </span>
                            </span>
                            <span className="text-sm font-semibold tabular-nums">{money(option)}</span>
                        </label>
                    ))}
                </div>
            )}

            {/* The store's cost, not the customer's — they pay the flat fee whatever
                is picked here. Worth saying, or the prices read like a charge. */}
            {!loading && (quote?.options.length ?? 0) > 0 && <p className="mt-3 text-xs text-neutral-500">{t('admin.orders.shipping.costNote')}</p>}

            <div className="mt-5 flex justify-end gap-2">
                <Button variant="secondary" onClick={onClose} disabled={busy}>
                    {t('admin.common.cancel')}
                </Button>
                <Button variant="primary" icon={Truck} disabled={busy || loading} onClick={() => onConfirm(choice)}>
                    {t('admin.orders.show.ship')}
                </Button>
            </div>
        </Modal>
    );
}
