import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useLocalized } from '@/lib/localize';

interface Category {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    image: string | null;
}

/**
 * "الأصناف" homepage section — a row of featured category tiles (image + label)
 * linking to the filtered catalogue. Driven by categories that carry an image
 * (set in the seeder for now; admin-manageable later). A faint geometric band
 * (Asset 5 2 stacked over 5 1, mirror-appended into a seamless tile) runs along
 * the bottom at full screen width, behind the tiles. Tile images are transparent
 * so the band shows through them.
 */
export default function CategoriesSection({ categories }: { categories: Category[] }) {
    const { t } = useTranslation();
    const localized = useLocalized();

    if (categories.length === 0) return null;

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
                <h2 className="mb-10 text-center font-heading font-black text-brand-gold text-[clamp(1.75rem,4vw,2.75rem)]">
                    {t('categoriesSection.title')}
                </h2>

                <div className="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-5">
                    {categories.map((c) => (
                        <Link key={c.id} href={`/?category=${c.slug}`} className="group flex flex-col items-center">
                            {/* Bottom-aligned so tiles of differing heights share a baseline. */}
                            <div className="flex h-40 w-full items-end justify-center lg:h-52">
                                {c.image ? (
                                    <img
                                        src={c.image}
                                        alt={localized(c, 'name')}
                                        className="max-h-full w-auto object-contain transition-transform duration-300 group-hover:-translate-y-1"
                                    />
                                ) : (
                                    <span className="text-5xl">🌴</span>
                                )}
                            </div>
                            <h3 className="mt-4 text-center font-heading font-bold text-brand-teal text-[clamp(0.95rem,1.8vw,1.25rem)]">
                                {localized(c, 'name')}
                            </h3>
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}
