import { describe, expect, it } from 'vitest';

import { compareCarriers, priceOf, sortCarriers, type SortableCarrier } from './sort';

/** A carrier with only the fields the order depends on. */
function carrier(name: string, opts: Partial<SortableCarrier> = {}): SortableCarrier & { name: string } {
    return { name, is_enabled: true, is_favourite: false, cheapest: 10, ...opts };
}

const names = (list: { name: string }[]) => list.map((c) => c.name);

describe('compareCarriers', () => {
    it('puts switched-off carriers at the end, however cheap they are', () => {
        const sorted = sortCarriers([carrier('off-cheap', { is_enabled: false, cheapest: 1 }), carrier('on-dear', { cheapest: 99 })]);

        expect(names(sorted)).toEqual(['on-dear', 'off-cheap']);
    });

    it('puts pinned carriers first among the ones still switched on', () => {
        const sorted = sortCarriers([carrier('cheap'), carrier('pinned', { is_favourite: true, cheapest: 50 })]);

        expect(names(sorted)).toEqual(['pinned', 'cheap']);
    });

    it('sorts by price inside each group', () => {
        const sorted = sortCarriers([
            carrier('pinned-dear', { is_favourite: true, cheapest: 40 }),
            carrier('plain-cheap', { cheapest: 5 }),
            carrier('pinned-cheap', { is_favourite: true, cheapest: 20 }),
            carrier('plain-dear', { cheapest: 30 }),
        ]);

        expect(names(sorted)).toEqual(['pinned-cheap', 'pinned-dear', 'plain-cheap', 'plain-dear']);
    });

    /**
     * 🔑 The case the two requests collide on. Off outranks pinned, so a carrier
     * someone pinned and later switched off still leaves the quick-access band.
     */
    it('keeps a pinned carrier at the end once it is switched off', () => {
        const sorted = sortCarriers([
            carrier('pinned-off', { is_favourite: true, is_enabled: false, cheapest: 1 }),
            carrier('plain-on', { cheapest: 80 }),
        ]);

        expect(names(sorted)).toEqual(['plain-on', 'pinned-off']);
    });

    /** Pinning still orders the off group, so the end of the list is not arbitrary. */
    it('pins within the switched-off group too', () => {
        const sorted = sortCarriers([
            carrier('off-plain', { is_enabled: false, cheapest: 5 }),
            carrier('off-pinned', { is_enabled: false, is_favourite: true, cheapest: 60 }),
        ]);

        expect(names(sorted)).toEqual(['off-pinned', 'off-plain']);
    });

    it('sinks a carrier OTO is not offering, without an availability clause', () => {
        expect(priceOf(carrier('gone', { cheapest: null }))).toBe(Infinity);

        const sorted = sortCarriers([carrier('unavailable', { cheapest: null }), carrier('priced', { cheapest: 90 })]);

        expect(names(sorted)).toEqual(['priced', 'unavailable']);
    });

    it('is a consistent comparator, so the sort is stable across engines', () => {
        const a = carrier('a', { is_favourite: true });
        const b = carrier('b', { cheapest: 5 });

        expect(compareCarriers(a, b)).toBeLessThan(0);
        expect(compareCarriers(b, a)).toBeGreaterThan(0);
        expect(compareCarriers(a, a)).toBe(0);
    });

    it('does not reorder the array it was given', () => {
        const list = [carrier('z', { cheapest: 99 }), carrier('a', { cheapest: 1 })];
        sortCarriers(list);

        expect(names(list)).toEqual(['z', 'a']);
    });
});
