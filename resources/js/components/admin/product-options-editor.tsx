import { useAdminT } from '@/i18n/use-admin-t';
import { applyAutoScaling, baseOption, scaledPrice, type ScalableOption } from '@/lib/option-pricing';
import { RotateCcw, Trash2 } from 'lucide-react';

export interface OptionRow {
    id?: number;
    label_ar: string;
    label_en: string;
    amount: number | null; // grams; null = box / non-weight (priced manually)
    price: number;
    price_overridden: boolean;
    stock_units: number; // kept for the DB; stock handling is deferred, so hidden here
    is_active: boolean;
    is_box: boolean; // a box is priced by hand, its size is optional, and there is at most one
}

const INPUT = 'w-full rounded border border-neutral-300 px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800';

// The header and every row use the SAME 5-column template so the two separate
// grids line up. A fixed last column (not `auto`) is what keeps them aligned:
// with `auto`, the header's empty actions cell and a row's filled one size
// differently, which shifts every other column. Literal strings — Tailwind scans
// source text, so this can't be a variable.

const baseRow = (): Omit<OptionRow, 'label_ar' | 'label_en' | 'amount' | 'price'> => ({
    price_overridden: false,
    stock_units: 1,
    is_active: true,
    is_box: false,
});

/**
 * Editor for a product's size / packaging options.
 *
 * Options are added by PRESET buttons, not blank rows:
 *   - weight presets (500g / 1kg / Custom) auto-fill label + grams, and their
 *     price auto-scales from the smallest size (editable → overridden, ↺ resets);
 *   - a Box is priced BY HAND (mandatory) with an OPTIONAL manual size, and is
 *     never auto-scaled.
 *
 * The first weight option seeds its price from the product's own price. Stock is
 * deferred (the `stock_units` column stays at 1 and is not shown here).
 */
export default function ProductOptionsEditor({
    value,
    onChange,
    basePrice,
}: {
    value: OptionRow[];
    onChange: (rows: OptionRow[]) => void;
    basePrice: number;
}) {
    const { t } = useAdminT();

    const scalable = (rows: OptionRow[]): ScalableOption[] =>
        rows.map((r) => ({ amount: r.amount, price: r.price, price_overridden: r.price_overridden }));

    // Recompute non-overridden weight prices from the smallest size.
    const rescale = (rows: OptionRow[]): OptionRow[] => {
        const scaled = applyAutoScaling(scalable(rows));
        return rows.map((r, i) => ({ ...r, price: scaled[i].price }));
    };

    // Auto price for a new weight option: scaled from the current smallest, or —
    // when it's the first weight option — seeded from the product's own price.
    const seedPrice = (amount: number): number => {
        const base = baseOption(scalable(value));
        return base ? (scaledPrice(base, { amount, price: 0, price_overridden: false }) ?? basePrice) : basePrice;
    };

    const addWeight = (amount: number, label_ar: string, label_en: string) => {
        onChange(rescale([...value, { ...baseRow(), label_ar, label_en, amount, price: seedPrice(amount) }]));
    };

    const addBox = () => {
        // Manual price (starts 0, marked overridden so it is never auto-touched),
        // optional size, and only one per product.
        onChange([...value, { ...baseRow(), label_ar: 'كرتون', label_en: 'Box', amount: null, price: 0, price_overridden: true, is_box: true }]);
    };

    const hasBox = value.some((r) => r.is_box);

    const addCustom = () => {
        onChange([...value, { ...baseRow(), label_ar: '', label_en: '', amount: null, price: 0, price_overridden: false }]);
    };

    const update = (i: number, patch: Partial<OptionRow>) => onChange(rescale(value.map((r, j) => (j === i ? { ...r, ...patch } : r))));

    const setPrice = (i: number, price: number) => {
        // Typing a price overrides THIS row, then rescale so a change to the base
        // (smallest) size flows to the non-overridden sizes.
        onChange(rescale(value.map((r, j) => (j === i ? { ...r, price, price_overridden: true } : r))));
    };

    const resetAuto = (i: number) => onChange(rescale(value.map((r, j) => (j === i ? { ...r, price_overridden: false } : r))));
    const remove = (i: number) => onChange(rescale(value.filter((_, j) => j !== i)));

    const hasAmount = (amount: number) => value.some((r) => r.amount === amount);

    const presetBtn =
        'rounded-full border border-neutral-300 px-3 py-1.5 text-sm font-medium transition-colors hover:border-brand-teal/50 disabled:opacity-40 dark:border-neutral-700';

    return (
        <div className="space-y-3">
            <div>
                <p className="text-sm font-semibold">{t('admin.products.options.title')}</p>
                <p className="text-xs text-neutral-500">{t('admin.products.options.hint')}</p>
            </div>

            <div className="flex flex-wrap gap-2">
                <button type="button" onClick={() => addWeight(500, '500غم', '500g')} disabled={hasAmount(500)} className={presetBtn}>
                    + 500g
                </button>
                <button type="button" onClick={() => addWeight(1000, '1كجم', '1kg')} disabled={hasAmount(1000)} className={presetBtn}>
                    + 1kg
                </button>
                <button type="button" onClick={addBox} disabled={hasBox} className={presetBtn}>
                    + {t('admin.products.options.box')}
                </button>
                <button type="button" onClick={addCustom} className={presetBtn}>
                    + {t('admin.products.options.custom')}
                </button>
            </div>

            {value.length === 0 ? (
                <p className="rounded border border-dashed border-neutral-300 px-3 py-4 text-center text-xs text-neutral-500 dark:border-neutral-700">
                    {t('admin.products.options.empty')}
                </p>
            ) : (
                <div className="space-y-2">
                    <div className="hidden grid-cols-[1fr_1fr_5rem_6rem_7rem] gap-2 text-xs text-neutral-500 sm:grid">
                        <span>{t('admin.products.options.labelAr')}</span>
                        <span>{t('admin.products.options.labelEn')}</span>
                        <span>{t('admin.products.options.grams')}</span>
                        <span>{t('admin.products.options.price')}</span>
                        <span />
                    </div>
                    {value.map((row, i) => (
                        <div
                            key={row.id ?? `new-${i}`}
                            className="grid grid-cols-2 gap-2 rounded-lg border border-neutral-200 p-2 sm:grid-cols-[1fr_1fr_5rem_6rem_7rem] sm:items-center sm:border-0 sm:p-0 dark:border-neutral-800"
                        >
                            <input
                                className={INPUT}
                                placeholder={t('admin.products.options.labelArPh')}
                                value={row.label_ar}
                                onChange={(e) => update(i, { label_ar: e.target.value })}
                                dir="rtl"
                            />
                            <input
                                className={INPUT}
                                placeholder={t('admin.products.options.labelEnPh')}
                                value={row.label_en}
                                onChange={(e) => update(i, { label_en: e.target.value })}
                            />
                            <input
                                className={INPUT}
                                type="number"
                                min={0}
                                placeholder={row.amount == null ? t('admin.products.options.optional') : t('admin.products.options.gramsPh')}
                                value={row.amount ?? ''}
                                onChange={(e) => update(i, { amount: e.target.value === '' ? null : Number(e.target.value) })}
                            />
                            <div className="relative">
                                <input
                                    className={`${INPUT} ${row.price_overridden && row.amount != null ? 'border-brand-gold/60' : ''}`}
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={row.price}
                                    onChange={(e) => setPrice(i, Number(e.target.value))}
                                />
                                {/* ↺ only for a weight option that has been overridden — a box
                                    has no auto price to return to. */}
                                {row.price_overridden && row.amount != null && !row.is_box && (
                                    <button
                                        type="button"
                                        onClick={() => resetAuto(i)}
                                        title={t('admin.products.options.resetAuto')}
                                        className="text-brand-teal absolute end-1 top-1/2 -translate-y-1/2 hover:opacity-70"
                                    >
                                        <RotateCcw className="h-3.5 w-3.5" />
                                    </button>
                                )}
                            </div>
                            <div className="col-span-2 flex items-center justify-end gap-3 sm:col-span-1">
                                <label className="inline-flex items-center gap-1 text-xs text-neutral-500">
                                    <input type="checkbox" checked={row.is_active} onChange={(e) => update(i, { is_active: e.target.checked })} />
                                    {t('admin.products.options.active')}
                                </label>
                                <button
                                    type="button"
                                    onClick={() => remove(i)}
                                    title={t('admin.common.remove')}
                                    className="text-red-500 hover:opacity-70"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
