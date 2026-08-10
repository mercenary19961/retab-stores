import { type CSSProperties } from 'react';
import { useTranslation } from 'react-i18next';

interface Value {
    key: string;
    label: string;
    body: string;
}

/**
 * About Us → "قيمنا" (Figma: "about us - values").
 *
 * Four brand values, each a gold-ringed pill with a one-line explanation beneath
 * it. Everything here is measured off the 1440×1136 composite rather than eyed,
 * and the horizontal geometry came out exact:
 *
 *   margin 181 + pill 376 + gap 326 + pill 376 + margin 181 = 1440
 *
 * ⚠️ Those numbers can NOT be used directly as a grid, and that trap is worth
 * knowing: a 376px column is narrower than the descriptions it has to hold (the
 * widest measures 537px of ink), so text sized to the design would wrap. What
 * actually has to be preserved is the pill CENTRES, at ±351px from the middle.
 * For a 2-column grid the centre offset is (column + gap) / 2, so column + gap is
 * pinned at 702 while the pair can be traded off freely — 620 + 82 puts the
 * centres in exactly the right place inside a 1322px container (margins 59) and
 * leaves each column wide enough for the copy. The pill then takes 376/620 of its
 * column and is centred, so it lands pixel-for-pixel where the design has it.
 *
 * Type sizes are derived from ink measurements too, and each is confirmed by two
 * independent dimensions agreeing: labels 59px, descriptions 58px (nearly the
 * same size — that is the design, not a mistake), heading 110px.
 *
 * 🔑 Everything above describes the SOURCE FRAME, which is 1136px tall and so
 * cannot fit a laptop viewport once the 114px navbar is accounted for. The
 * section therefore renders at **0.85 of the design's type and pill size, with
 * the whitespace roughly halved**, which lands it at ~730px. The split is
 * deliberate: nearly half the source frame's height (531 of 1136) is padding and
 * gaps, so cutting whitespace buys the most height for the least fidelity, and
 * scaling type and pill by the SAME factor keeps the pill's 3.13:1 proportion
 * intact — squeezing height alone would have flattened it into a wide tag.
 *
 * Every `clamp()` here reaches its cap at exactly 1440px, so the section's height
 * stops growing beyond that width and stays a fixed ~730px on larger monitors
 * rather than scaling itself back out of the viewport.
 */
export default function AboutValues() {
    const { t } = useTranslation();

    // i18next returns the raw array for an array-valued key; the guard stops a
    // missing or mistyped key from throwing at render.
    const raw = t('about.values.items', { returnObjects: true, defaultValue: [] }) as unknown;
    const values = (Array.isArray(raw) ? raw : []) as Value[];

    return (
        <section className="w-full bg-white pt-[clamp(1rem,2.2vw,2rem)] pb-[clamp(1.75rem,4.9vw,4.4rem)]">
            <div className="mx-auto w-[91.81%] max-w-[1322px]">
                <h2 className="text-brand-gold font-heading text-center text-[clamp(1.9rem,6.5vw,5.85rem)] leading-[1.2] font-black">
                    {t('about.values.heading')}
                </h2>

                {/* `gap-x` is 82/1322 of the container — see the centre-offset note
                    above; it is what places the pills, not the pill width itself.
                    Collapses to one column below `sm`, where two 26%-wide pills
                    would be far too narrow to read. */}
                <ul className="mt-[clamp(1rem,3.9vw,3.5rem)] grid grid-cols-1 gap-x-[6.2%] gap-y-[clamp(1.5rem,6.6vw,6rem)] sm:grid-cols-2">
                    {values.map((value, i) => (
                        <li key={value.key} className="flex flex-col items-center">
                            {/* 376/620 of the column on desktop, reproducing the
                                design's fixed-width pill: all four are the same
                                width regardless of label length, which is what
                                makes the set read as a row rather than as tags.

                                ⚠️ NOT full-width on mobile, which was the first
                                attempt: in a single column that produced a
                                358×34 bar, a 10:1 box that no longer reads as a
                                pill at all. The 42%/51.6% pair plus the raised
                                clamp floors below hold the design's 3.13:1
                                proportion at every breakpoint (measured 3.33 at
                                390px, 3.11 at 768px, 3.13 at 1440px).

                                ⚠️ The SVG exports these with `fill="black"`, which
                                is a Figma placeholder — sampling the PNG shows a
                                WHITE interior carrying a faint inset shadow
                                (#F6F6F6 at the ring fading to #FEFEFE at the
                                centre, darkest top-left). Figma does not export
                                effects to SVG, the same gotcha as the hero card's
                                frosted blur, so the shadow is rebuilt in CSS.
                                Border width scales but never below a hairline.

                                ⚠️ `whitespace-nowrap` is load-bearing, not tidiness.
                                The pill is a FIXED width, so a label that wraps
                                does not reflow inside it — it grows the pill's
                                height and the second line spills past the rounded
                                ends. "Customer Care" did exactly that. English is
                                given a smaller size because Latin words are much
                                longer than these Arabic labels and the widest one
                                has to fit the same pill. */}
                            {/* The width moved from the pill onto this wrapper so
                                the overlay can be positioned against it. With no
                                padding or border of its own, its content box is
                                exactly the pill's border box, which is what glues
                                the travelling light to the gold outline — an
                                `inset-0` overlay inside the pill would instead be
                                positioned against its PADDING box and sit inside
                                the border. */}
                            {/* The gold outline is a rotating conic gradient rather
                                than a flat border, so two highlights sweep round it
                                like light on metal (see `.gradient-border`).

                                The three gradient stops stay in the gold family and
                                include a DARK one, which is what keeps it visible on
                                a white pill — the earlier attempt at a single pale
                                travelling light failed precisely because nothing can
                                be brighter than a white background, so it read as
                                the border washing out. `--gb-mid` is the brand gold,
                                i.e. the design's resting border colour, so the pill
                                still looks like the Figma between highlights.

                                `--gb-fill` must match the pill's own fill: the
                                technique paints that fill as a background layer
                                clipped to the padding box, and `bg-white` stays as a
                                fallback underneath. `--gb-index` offsets each pill's
                                phase so the group shimmers as a wave rather than in
                                lockstep — from the array order, which is reading
                                order, so it needs no direction-specific code. */}
                            <div className="relative w-[42%] sm:w-[51.6%]">
                                <p
                                    className="gradient-border text-brand-teal font-heading w-full rounded-full bg-white pt-[calc(var(--pad)-var(--nudge))] pb-[calc(var(--pad)+var(--nudge))] text-center text-[clamp(1.35rem,3.48vw,3.13rem)] leading-[1.2] font-black whitespace-nowrap shadow-[inset_3px_4px_14px_rgba(0,0,0,0.05)] [--gb-bright:#e3c98c] [--gb-deep:#6f5a30] [--gb-mid:var(--color-brand-gold)] [--gb-width:clamp(2px,0.28vw,4px)] [--nudge:0.11em] [--pad:0.349em] ltr:text-[clamp(1.1rem,2.7vw,2.45rem)] ltr:[--nudge:0.02em]"
                                    style={{ '--gb-index': i } as CSSProperties}
                                >
                                    {value.label}
                                </p>
                            </div>

                            {/* ⚠️ `text-center`, and the pill above is centred too,
                                because these are physically centred in the design
                                rather than aligned to the reading direction — so
                                there is deliberately no `text-start` here (see the
                                journey section, where the opposite was true and
                                `text-end` had it backwards in both locales). */}
                            <p className="text-brand-teal mt-[clamp(0.3rem,0.85vw,0.8rem)] text-center text-[clamp(1.05rem,3.42vw,3.06rem)] leading-[1.35] font-medium">
                                {value.body}
                            </p>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
