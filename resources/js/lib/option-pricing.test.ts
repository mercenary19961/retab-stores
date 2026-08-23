import { describe, expect, it } from 'vitest';

import { applyAutoScaling, baseOption, scaledPrice, type ScalableOption } from './option-pricing';

const opt = (amount: number | null, price: number, price_overridden = false): ScalableOption => ({ amount, price, price_overridden });

describe('baseOption', () => {
    it('is the smallest-amount weight option', () => {
        const opts = [opt(1000, 40), opt(250, 10), opt(500, 20)];
        expect(baseOption(opts)?.amount).toBe(250);
    });

    it('ignores non-weight (carton) options', () => {
        expect(baseOption([opt(null, 69), opt(500, 20)])?.amount).toBe(500);
    });

    it('is null when nothing has a weight', () => {
        expect(baseOption([opt(null, 69)])).toBeNull();
    });
});

describe('scaledPrice', () => {
    it('scales linearly by weight from the base', () => {
        const base = opt(250, 5.75);
        expect(scaledPrice(base, opt(500, 0))).toBe(11.5);
        expect(scaledPrice(base, opt(1000, 0))).toBe(23);
    });

    it('rounds to 2 decimals', () => {
        expect(scaledPrice(opt(250, 3.33), opt(1000, 0))).toBe(13.32);
    });

    it('does not scale a non-weight option', () => {
        expect(scaledPrice(opt(250, 5), opt(null, 0))).toBeNull();
    });
});

describe('applyAutoScaling', () => {
    it('recomputes weight options and leaves the base + carton alone', () => {
        const out = applyAutoScaling([opt(250, 5), opt(500, 999), opt(1000, 999), opt(null, 69)]);
        expect(out.map((o) => o.price)).toEqual([5, 10, 20, 69]); // 250 base, 500→10, 1000→20, carton untouched
    });

    it('never touches an overridden option', () => {
        const out = applyAutoScaling([opt(250, 5), opt(500, 12, true), opt(1000, 999)]);
        expect(out[1].price).toBe(12); // manual, kept
        expect(out[2].price).toBe(20); // auto, recomputed
    });

    it('is a no-op when there is no weight base', () => {
        const out = applyAutoScaling([opt(null, 69), opt(null, 120)]);
        expect(out.map((o) => o.price)).toEqual([69, 120]);
    });
});
