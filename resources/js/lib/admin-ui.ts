/**
 * Shared class strings for the admin panel's chrome.
 *
 * 🔑 These are constants rather than components because the markup around them
 * varies (a card is sometimes a `<section>` with padding, sometimes a `<div>` with
 * `overflow-hidden` and its own header row). What has to be shared is the surface
 * recipe, not the element — and before this existed the panel carried 44 card
 * shells at one radius while the redesigned dashboard used another.
 *
 * Compose with your own spacing: `className={`${CARD} p-5`}`.
 */

/** Panel surface. Radius matches the dashboard's redesign; padding is the caller's. */
export const CARD = 'rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900';

/**
 * Table header row.
 *
 * ⚠️ `text-start`, never `text-left`. The admin flips to `dir="rtl"` in Arabic, so
 * a physical `left` pinned every header to the opposite side from its own column's
 * data — which is how all 17 tables in the panel looked in Arabic until 2026-08-20.
 */
export const THEAD =
    'border-b border-neutral-200 bg-neutral-50 text-start text-neutral-600 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-300';
