import { describe, expect, it } from 'vitest';

import { emptyMap, matchingPreset, samePermissionMap, type PermissionMap, type PermissionSchema } from './permissions';

// A miniature stand-in for App\Support\Permission::SCHEMA. Deliberately small:
// what is being tested is the comparison, not the real catalogue.
const SCHEMA: PermissionSchema = {
    orders: ['view', 'manage', 'export'],
    products: ['view', 'edit'],
    customers: ['view'],
};

/** An all-off map with the named sections overridden. */
const map = (o: PermissionMap = {}): PermissionMap => ({ ...emptyMap(SCHEMA), ...o });

describe('emptyMap', () => {
    it('turns every switch in the schema off', () => {
        expect(emptyMap(SCHEMA)).toEqual({
            orders: { view: false, manage: false, export: false },
            products: { view: false, edit: false },
            customers: { view: false },
        });
    });
});

describe('samePermissionMap', () => {
    it('matches identical maps', () => {
        const a = map({ orders: { view: true, manage: true, export: false } });
        expect(samePermissionMap(SCHEMA, a, structuredClone(a))).toBe(true);
    });

    it('notices a single switch differing', () => {
        const a = map({ orders: { view: true, manage: true, export: false } });
        const b = map({ orders: { view: true, manage: true, export: true } });
        expect(samePermissionMap(SCHEMA, a, b)).toBe(false);
    });

    /**
     * Key order comes from whichever PHP array built the value, so a comparison
     * that depended on it (JSON.stringify, say) would be quietly fragile.
     */
    it('ignores key order', () => {
        const a: PermissionMap = {
            orders: { export: false, view: true, manage: false },
            products: { edit: false, view: true },
            customers: { view: false },
        };
        const b: PermissionMap = {
            customers: { view: false },
            products: { view: true, edit: false },
            orders: { view: true, manage: false, export: false },
        };
        expect(samePermissionMap(SCHEMA, a, b)).toBe(true);
    });

    it('reads a missing section as denied rather than throwing', () => {
        const partial: PermissionMap = { orders: { view: true, manage: false, export: false } };
        expect(samePermissionMap(SCHEMA, partial, map({ orders: { view: true, manage: false, export: false } }))).toBe(true);
        expect(samePermissionMap(SCHEMA, partial, map({ orders: { view: true, manage: false, export: false }, customers: { view: true } }))).toBe(
            false,
        );
    });

    it('only compares what the schema names, so a stray extra key cannot break a match', () => {
        const a = map({});
        const b: PermissionMap = { ...emptyMap(SCHEMA), ghost: { view: true } };
        expect(samePermissionMap(SCHEMA, a, b)).toBe(true);
    });
});

describe('matchingPreset', () => {
    const presets: Record<string, PermissionMap> = {
        operations: map({ orders: { view: true, manage: true, export: true }, customers: { view: true } }),
        catalogue: map({ products: { view: true, edit: true } }),
        none: emptyMap(SCHEMA),
    };

    it('names the preset the grid equals', () => {
        expect(matchingPreset(SCHEMA, structuredClone(presets.catalogue), presets)).toBe('catalogue');
        expect(matchingPreset(SCHEMA, emptyMap(SCHEMA), presets)).toBe('none');
    });

    /** Hand-toggling one switch away from a preset must put the highlight out. */
    it('returns null once the grid drifts off a preset', () => {
        const drifted = structuredClone(presets.catalogue);
        drifted.orders.view = true;
        expect(matchingPreset(SCHEMA, drifted, presets)).toBeNull();
    });

    /** …and toggling back onto one must light it again, which is the whole ask. */
    it('lights up again when hand-picked switches add up to a preset', () => {
        const built = emptyMap(SCHEMA);
        built.orders.view = true;
        built.orders.manage = true;
        built.orders.export = true;
        expect(matchingPreset(SCHEMA, built, presets)).toBeNull();

        built.customers.view = true;
        expect(matchingPreset(SCHEMA, built, presets)).toBe('operations');
    });
});
