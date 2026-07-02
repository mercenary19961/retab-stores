import { Head, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocalized } from '@/lib/localize';
import StoreLayout from '@/layouts/store-layout';

interface ReturnableItem {
    order_item_id: number;
    name_ar: string;
    name_en: string | null;
    quantity: number;
}

export default function ReturnRequest({
    order,
    items,
    windowDays,
}: {
    order: { order_number: string; delivered_at: string | null };
    items: ReturnableItem[];
    windowDays: number;
}) {
    const { t } = useTranslation();
    const localized = useLocalized();

    const [quantities, setQuantities] = useState<Record<number, number>>({});
    const [reason, setReason] = useState('');
    const [photos, setPhotos] = useState<File[]>([]);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const submit = (e: FormEvent) => {
        e.preventDefault();

        // File uploads → native FormData with forceFormData (project convention).
        const form = new FormData();
        form.append('reason', reason);
        items.forEach((item, i) => {
            form.append(`items[${i}][order_item_id]`, String(item.order_item_id));
            form.append(`items[${i}][quantity]`, String(quantities[item.order_item_id] ?? 0));
        });
        photos.slice(0, 5).forEach((file, i) => form.append(`photos[${i}]`, file));

        router.post(`/orders/${order.order_number}/return`, form, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (errs) => setErrors(errs as Record<string, string>),
        });
    };

    return (
        <StoreLayout>
            <Head title={t('returns.title')} />

            <div className="mx-auto max-w-xl">
                <h1 className="mb-1 text-2xl font-bold">{t('returns.title')}</h1>
                <p className="mb-1 text-sm text-gray-500">
                    {t('order.orderNumber')}: <span className="font-mono">{order.order_number}</span>
                </p>
                <p className="mb-6 text-sm text-gray-600">{t('returns.windowNote', { days: windowDays })}</p>

                <form onSubmit={submit} className="space-y-5 rounded-lg border border-gray-200 bg-white p-5">
                    <div>
                        <p className="mb-2 text-sm font-semibold">{t('returns.itemsHeading')}</p>
                        <div className="space-y-2">
                            {items.map((item) => (
                                <div key={item.order_item_id} className="flex items-center justify-between gap-3 rounded border border-gray-100 px-3 py-2">
                                    <span className="text-sm">{localized(item, 'name')}</span>
                                    <label className="flex items-center gap-2 text-xs text-gray-500">
                                        {t('returns.qty')}
                                        <input
                                            type="number"
                                            min={0}
                                            max={item.quantity}
                                            value={quantities[item.order_item_id] ?? 0}
                                            onChange={(e) =>
                                                setQuantities((q) => ({
                                                    ...q,
                                                    [item.order_item_id]: Math.min(item.quantity, Math.max(0, Number(e.target.value))),
                                                }))
                                            }
                                            className="w-16 rounded border border-gray-300 px-2 py-1 text-center text-sm"
                                        />
                                        / {item.quantity}
                                    </label>
                                </div>
                            ))}
                        </div>
                        {errors.items && <p className="mt-1 text-xs text-red-500">{errors.items}</p>}
                    </div>

                    <label className="block">
                        <span className="text-sm font-semibold">{t('returns.reason')}</span>
                        <textarea
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder={t('returns.reasonPlaceholder')}
                            rows={4}
                            className="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        />
                        {errors.reason && <span className="text-xs text-red-500">{errors.reason}</span>}
                    </label>

                    <label className="block">
                        <span className="text-sm font-semibold">{t('returns.photos')}</span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            onChange={(e) => setPhotos(Array.from(e.target.files ?? []).slice(0, 5))}
                            className="mt-1 w-full text-sm"
                        />
                        {photos.length > 0 && <span className="text-xs text-gray-500">{photos.length} / 5</span>}
                        {errors.photos && <span className="block text-xs text-red-500">{errors.photos}</span>}
                    </label>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-lg bg-[#2f4f4f] px-6 py-3 font-semibold text-white transition hover:bg-[#264141] disabled:opacity-60"
                    >
                        {t('returns.submit')}
                    </button>
                </form>
            </div>
        </StoreLayout>
    );
}
