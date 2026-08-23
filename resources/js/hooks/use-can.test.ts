/*
 * useCan is a thin, synchronous selector over usePage() — no state, no effects —
 * so it is exercised here by calling it directly rather than through a rendered
 * component. That trips rules-of-hooks, which cannot know the hook holds nothing
 * React-stateful; the disable is scoped to this unit test only.
 */
/* eslint-disable react-hooks/rules-of-hooks */
import { describe, expect, it, vi } from 'vitest';

// usePage is the only external dependency; stub it per case.
const pageProps = vi.hoisted(() => ({ current: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: pageProps.current }),
}));

import { useCan } from './use-can';

/** useCan reads usePage() synchronously, so calling it outside a component is fine here. */
function canFor(auth: Record<string, unknown>) {
    pageProps.current = { auth };
    return useCan();
}

describe('useCan', () => {
    it('grants everything to an admin (permissions arrive as null)', () => {
        const can = canFor({ user: { role: 'admin' }, permissions: null });
        expect(can('products.delete')).toBe(true);
        expect(can('settings.edit')).toBe(true);
        expect(can('anything.at.all')).toBe(true);
    });

    it('checks an editor against their resolved map', () => {
        const can = canFor({
            user: { role: 'editor' },
            permissions: { products: { view: true, delete: false }, orders: { export: true } },
        });
        expect(can('products.view')).toBe(true);
        expect(can('products.delete')).toBe(false);
        expect(can('orders.export')).toBe(true);
    });

    it('denies an action the map does not name', () => {
        const can = canFor({ user: { role: 'editor' }, permissions: { products: { view: true } } });
        expect(can('products.delete')).toBe(false);
        expect(can('coupons.delete')).toBe(false); // whole section absent
    });

    it('denies everything when there is no permission map and no admin role', () => {
        const can = canFor({ user: { role: 'customer' }, permissions: null });
        expect(can('products.view')).toBe(false);
    });

    it('is robust to a missing auth block', () => {
        pageProps.current = {};
        const can = useCan();
        expect(can('products.delete')).toBe(false);
    });
});
