import { type CSSProperties } from 'react';

/**
 * A cream light that runs once around a pill-shaped element's outline, then hands
 * off to the next one in the group. Drop it into any `relative` wrapper whose
 * content box matches the target's border box, and pass the item's index.
 *
 * The timing and the reduced-motion opt-out live in `app.css` (`.border-light`) —
 * including why this is a dash offset rather than the more familiar rotating
 * conic-gradient, which moves unevenly on an elongated shape.
 *
 * ⚠️ `preserveAspectRatio="none"` stretches the viewBox to fill the element, which
 * is what keeps the path glued to the pill without measuring anything. That is
 * safe here only because the pill's aspect barely moves across breakpoints
 * (measured 3.33 at 390px, 3.11 at 768px, 3.10 at 1440px). The viewBox is 3.13:1,
 * so the worst anisotropy is about 6% — invisible on a soft glow, but it would
 * matter for a shape whose proportions actually change.
 *
 * 🔑 The colours are PARAMETERS (`--light-core` / `--light-halo`) because what
 * reads as "a light" depends entirely on the surface, and getting this wrong is
 * not a subtle miss. A pale cream arc was tried first — the obvious choice, and
 * correct over a dark plate — but on the values section's WHITE pill it was almost
 * invisible, and where it did register it looked like the gold border was breaking
 * up rather than lighting up. On a light surface nothing can be brighter than the
 * background, so the travelling point has to be more SATURATED instead.
 */

// Pill path for the 313x100 viewBox, inset 2 units so the stroke sits on the
// border rather than straddling the element's edge. Radius 48 = half the inset
// height, which is what makes the ends semicircular instead of elliptical.
const PILL = 'M50 2 H263 A48 48 0 0 1 263 98 H50 A48 48 0 0 1 50 2 Z';

export default function BorderLight({ index }: { index: number }) {
    return (
        <svg
            aria-hidden
            viewBox="0 0 313 100"
            preserveAspectRatio="none"
            className="border-light pointer-events-none absolute inset-0 h-full w-full"
            style={{ '--light-index': index } as CSSProperties}
        >
            {/* Two strokes rather than a filter: a wide translucent halo under a
                narrow core reads as a glow at a fraction of the cost, and avoids
                animating a blur. Both inherit the dash from the <svg>. Defaults
                suit a dark surface; light surfaces override both. */}
            {/* ⚠️ The halo defaults to the CORE colour, not to a pale tint. A
                translucent light-coloured halo was tried and it recreated the very
                problem the core colour was chosen to avoid: on a white pill it is
                paler than the gold border, so it reads as the border washing out,
                and the bead looked like two separate marks — a dark dot with a
                bright smear beside it. Same hue, lower opacity, gives one bead with
                a soft edge on any surface. */}
            <path
                d={PILL}
                fill="none"
                stroke="var(--light-halo, var(--light-core, var(--color-brand-cream)))"
                strokeWidth="8"
                strokeOpacity="0.28"
                strokeLinecap="round"
                pathLength="100"
            />
            <path
                d={PILL}
                fill="none"
                stroke="var(--light-core, var(--color-brand-cream))"
                strokeWidth="3.5"
                strokeOpacity="0.95"
                strokeLinecap="round"
                pathLength="100"
            />
        </svg>
    );
}
