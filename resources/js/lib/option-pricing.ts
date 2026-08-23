/**
 * Auto-scaling for product size options.
 *
 * The rule (client's spec): every weight option's price is derived from the
 * SMALLEST option's price, proportional to weight — "all based on the original
 * price for the smallest amount". Each derived price is then overridable by hand,
 * and a non-weight option (a carton, amount = null) is always priced manually
 * because it has no weight to scale from.
 *
 * 🔑 This only SUGGESTS prices in the admin editor; whatever the admin confirms
 * is stored per option and is what the storefront and order snapshots read. So a
 * later change to the base price re-runs this on the auto options, never on the
 * overridden ones. Mirror of App\Support\OptionPricing (kept in step by
 * option-pricing.test.ts / OptionPricingTest.php).
 */

export interface ScalableOption {
    amount: number | null; // grams; null = non-weight (carton), never auto-scaled
    price: number;
    price_overridden: boolean;
}

/** Round to 2 decimals (SAR), avoiding binary-float drift like 10.005. */
export function round2(n: number): number {
    return Math.round((n + Number.EPSILON) * 100) / 100;
}

/**
 * The base = the smallest-amount weight option. Its price is the anchor every
 * other weight option scales from. Null when no weight option exists.
 */
export function baseOption(options: ScalableOption[]): ScalableOption | null {
    const weighted = options.filter((o) => o.amount != null && o.amount > 0);
    if (weighted.length === 0) return null;
    return weighted.reduce((a, b) => (a.amount! <= b.amount! ? a : b));
}

/**
 * The auto price for one option given the base, or null when it can't scale
 * (no base, the option is the base itself, or it has no weight).
 */
export function scaledPrice(base: ScalableOption | null, option: ScalableOption): number | null {
    if (!base || option.amount == null || option.amount <= 0) return null;
    return round2((base.price * option.amount) / base.amount!);
}

/**
 * Recompute every non-overridden weight option from the current base, leaving
 * overridden ones and the base itself untouched. Returns a new array.
 */
export function applyAutoScaling(options: ScalableOption[]): ScalableOption[] {
    const base = baseOption(options);
    if (!base) return options.map((o) => ({ ...o }));

    return options.map((o) => {
        if (o === base || o.price_overridden || o.amount == null || o.amount <= 0) {
            return { ...o };
        }
        return { ...o, price: scaledPrice(base, o) ?? o.price };
    });
}
