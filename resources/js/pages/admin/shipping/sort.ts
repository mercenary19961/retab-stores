/**
 * The order carriers are read in on /admin/shipping.
 *
 * Extracted from the page so it can be tested directly: the rule is three-way and
 * every part of it was asked for separately, which is exactly the kind of thing
 * that silently loses a clause during a later edit.
 *
 * The account lists sixteen carriers and the store realistically ships with two
 * or three, so "cheapest first" alone — the original rule — meant hunting for a
 * familiar name in a grid that reshuffles itself every time prices move.
 */

/** Only the fields the order depends on, so the comparator is testable alone. */
export interface SortableCarrier {
    is_enabled: boolean;
    is_favourite: boolean;
    cheapest: number | null;
}

/**
 * Cheapest price across a carrier's services.
 *
 * Infinity when OTO is not offering it at all, so an unavailable carrier sinks
 * within its group rather than leading it with a blank price.
 */
export function priceOf(carrier: SortableCarrier): number {
    return carrier.cheapest ?? Infinity;
}

/**
 * Switched-off carriers last · pinned first · then cheapest.
 *
 * 🔑 Enablement outranks pinning, and that resolves the one case where the two
 * requests collide: a carrier someone pinned and later switched off. "Off" means
 * the store will not ship with it, so it is not quick-access material whatever
 * anyone pinned last month — it belongs at the end with the rest of the off ones.
 * Pinning then orders what is left, which is where quick access actually helps.
 *
 * ⚠️ Availability is deliberately NOT a fourth clause. It is already carried by
 * the price: a carrier OTO is not offering has no price, so priceOf sends it to
 * the back of whichever group it is in. Adding an explicit clause would say the
 * same thing twice and give the two ways to disagree.
 */
export function compareCarriers(a: SortableCarrier, b: SortableCarrier): number {
    if (a.is_enabled !== b.is_enabled) return a.is_enabled ? -1 : 1;
    if (a.is_favourite !== b.is_favourite) return a.is_favourite ? -1 : 1;

    return priceOf(a) - priceOf(b);
}

/** A sorted copy. Never sorts in place: the caller's array is Inertia's prop. */
export function sortCarriers<T extends SortableCarrier>(carriers: T[]): T[] {
    return [...carriers].sort(compareCarriers);
}
