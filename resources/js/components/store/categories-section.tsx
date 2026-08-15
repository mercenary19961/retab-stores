import { useLocalized } from '@/lib/localize';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface Category {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    image: string | null;
}

/** Tiles per row below `lg`, where they no longer all fit on one line. */
const PER_ROW = 3;

/**
 * Where an incomplete final row starts, keyed by how many tiles it is short.
 *
 * Written as whole literal class names because Tailwind scans source text — a
 * built string like `col-start-${n}` produces no CSS at all.
 */
const LAST_ROW_START: Record<number, string> = {
    0: '',
    1: 'max-lg:col-start-2',
    2: 'max-lg:col-start-3',
};

/**
 * "الأصناف" homepage section — a row of featured category tiles (image + label)
 * linking to the filtered catalogue. Driven by categories that carry an image
 * (set in the seeder for now; admin-manageable later). A faint geometric band
 * (Asset 5 2 stacked over 5 1, mirror-appended into a seamless tile) runs along
 * the bottom at full screen width, behind the tiles. Tile images are transparent
 * so the band shows through them.
 *
 * 🔑 Below `lg` the grid is **6 columns with every tile spanning 2**, not 3
 * columns. That is what lets a short final row be nudged half a tile across so
 * it sits in the GAPS of the row above (5 tiles → 3 over 2, pyramid) instead of
 * leaving an orphan hanging off the start edge. The alignment is exact for any
 * gap size: with column `c` and gap `g`, row-one centres land at `c+½g`,
 * `3c+2½g`, `5c+4½g`, and an offset row-two tile at `2c+1½g` — precisely the
 * midpoint of the first pair.
 *
 * The offset is derived from the count rather than hardcoded to five, so adding
 * or removing a category cannot silently break the arrangement: 4 tiles centre
 * the last one, 6 tiles need no offset at all.
 */
export default function CategoriesSection({ categories }: { categories: Category[] }) {
    const { t } = useTranslation();
    const localized = useLocalized();

    if (categories.length === 0) return null;

    // `|| PER_ROW` so an exact multiple counts as a full final row, not an empty one.
    const inLastRow = categories.length % PER_ROW || PER_ROW;
    const lastRowFirstIndex = categories.length - inLastRow;
    const lastRowOffset = LAST_ROW_START[PER_ROW - inLastRow] ?? '';

    return (
        <section className="relative w-full overflow-hidden bg-white py-12 sm:py-16">
            {/* Full-viewport-width geometric band along the bottom, behind the tiles.
                The tile repeats seamlessly, so it fills any screen width without a seam. */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 bottom-0 h-[110px] bg-repeat-x sm:h-[140px]"
                style={{
                    backgroundImage: "url('/images/categories/pattern.webp')",
                    backgroundPosition: 'center bottom',
                    backgroundSize: 'auto 100%',
                }}
            />

            <div className="relative z-10 mx-auto max-w-[1600px] px-6 lg:px-12">
                <h2 className="font-heading text-brand-gold mb-10 text-center text-[clamp(1.75rem,4vw,2.75rem)] font-black">
                    {t('categoriesSection.title')}
                </h2>

                <div className="grid grid-cols-6 gap-x-3 gap-y-6 sm:gap-x-4 lg:grid-cols-5 lg:gap-y-8">
                    {categories.map((c, i) => (
                        <Link
                            key={c.id}
                            href={`/shop?category=${c.slug}`}
                            // Only the FIRST tile of a short final row is offset; the
                            // rest flow after it.
                            className={`group col-span-2 flex flex-col items-center lg:col-span-1 ${i === lastRowFirstIndex ? lastRowOffset : ''}`}
                        >
                            {/* Bottom-aligned so tiles of differing heights share a baseline. */}
                            <div className="flex h-28 w-full items-end justify-center sm:h-40 lg:h-52">
                                {c.image ? (
                                    <img
                                        src={c.image}
                                        alt={localized(c, 'name')}
                                        className="max-h-full w-auto object-contain transition-transform duration-300 group-hover:-translate-y-1"
                                    />
                                ) : (
                                    <span className="text-4xl sm:text-5xl">🌴</span>
                                )}
                            </div>
                            {/* Three across leaves ~105px per tile on a phone, so the
                                label steps down below `sm` to keep two-word names on
                                one line. */}
                            <h3 className="font-heading text-brand-teal mt-3 text-center text-[clamp(0.95rem,1.8vw,1.25rem)] font-bold max-sm:mt-2 max-sm:text-[0.8rem] max-sm:leading-tight">
                                {localized(c, 'name')}
                            </h3>
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}
