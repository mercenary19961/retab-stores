/**
 * Comparing an editor's permission grid against the named presets.
 *
 * The Staff page highlights whichever "Start from" chip the grid currently
 * EQUALS, rather than whichever one was last clicked. That distinction is the
 * whole point: pressing a chip lights it, drifting away by hand puts it out, and
 * toggling your way back onto a preset lights it again. So the highlight always
 * answers "what does this add up to right now?" instead of remembering a click
 * that may no longer describe anything.
 */

export type PermissionMap = Record<string, Record<string, boolean>>;
export type PermissionSchema = Record<string, string[]>;

/**
 * Every switch off.
 *
 * "Clear all" is deliberately not a server preset — it is the useful starting
 * point when granting only one or two sections, and it is derivable, so shipping
 * it would be one more thing to keep in step with the schema.
 */
export function emptyMap(schema: PermissionSchema): PermissionMap {
    return Object.fromEntries(Object.entries(schema).map(([section, actions]) => [section, Object.fromEntries(actions.map((a) => [a, false]))]));
}

/**
 * Whether two maps grant exactly the same thing.
 *
 * 🔑 Walks the SCHEMA rather than the objects' own keys, so neither key ORDER
 * nor a MISSING section can produce a wrong answer. A missing section reads as
 * denied here, which matches how an absent switch renders in the grid — note
 * that is NOT how the server resolves a stored map (there, an absent section
 * falls back to DEFAULTS and can therefore grant). The values compared here have
 * already been through that resolver, so the two never disagree.
 */
export function samePermissionMap(schema: PermissionSchema, a: PermissionMap, b: PermissionMap): boolean {
    return Object.entries(schema).every(([section, actions]) => actions.every((action) => !!a[section]?.[action] === !!b[section]?.[action]));
}

/**
 * The name of the preset the current grid equals, or null when it matches none.
 *
 * First match wins. The presets are distinct in practice, so this only decides
 * an order that cannot currently arise.
 */
export function matchingPreset(schema: PermissionSchema, current: PermissionMap, presets: Record<string, PermissionMap>): string | null {
    return Object.keys(presets).find((name) => samePermissionMap(schema, current, presets[name])) ?? null;
}
