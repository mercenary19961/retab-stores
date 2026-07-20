import { Head, Link, router } from '@inertiajs/react';
import { Search, Tag, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocalized } from '@/lib/localize';
import StoreLayout from '@/layouts/store-layout';
import ProductCard, { type StoreProduct } from '@/components/store/product-card';
import StorePagination, { type Paginator } from '@/components/store/pagination';

interface Category {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
}

interface Filters {
    q: string;
    sort: 'newest' | 'price_asc' | 'price_desc' | 'name';
    on_sale: boolean;
}

export default function ShopCatalogue({
    categories,
    products,
    activeCategory,
    filters,
}: {
    categories: Category[];
    products: Paginator<StoreProduct>;
    activeCategory: string | null;
    filters: Filters;
}) {
    const { t } = useTranslation();
    const localized = useLocalized();

    const [q, setQ] = useState(filters.q);

    // Merge the current filters with a patch, dropping empties, and produce the
    // query object. Category filters are real links (crawlable); the search / sort
    // / offers controls navigate via router.get with the same merge.
    const params = (patch: Record<string, string | undefined>) => {
        const merged: Record<string, string | undefined> = {
            category: activeCategory ?? undefined,
            q: filters.q || undefined,
            sort: filters.sort !== 'newest' ? filters.sort : undefined,
            on_sale: filters.on_sale ? '1' : undefined,
            ...patch,
        };
        return Object.fromEntries(
            Object.entries(merged).filter(([, v]) => v !== undefined && v !== ''),
        ) as Record<string, string>;
    };

    const hrefWith = (patch: Record<string, string | undefined>) => {
        const qs = new URLSearchParams(params(patch)).toString();
        return qs ? `/shop?${qs}` : '/shop';
    };

    // Toolbar interactions stay put (preserveScroll) so the grid doesn't jump.
    const go = (patch: Record<string, string | undefined>) =>
        router.get('/shop', params(patch), { preserveState: true, preserveScroll: true });

    const submitSearch = (e: React.FormEvent) => {
        e.preventDefault();
        go({ q: q.trim() || undefined });
    };

    const hasFilters = Boolean(filters.q || filters.on_sale || activeCategory || filters.sort !== 'newest');

    const chip = (active: boolean) =>
        `rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
            active ? 'bg-brand-teal text-white' : 'bg-brand-cream text-brand-teal hover:bg-brand-gold/20'
        }`;

    return (
        <StoreLayout>
            <Head title={t('catalogue.headTitle')}>
                <meta name="description" content={t('shop.metaDescription')} />
            </Head>

            <h1 className="mb-6 text-center font-heading font-black text-brand-teal text-[clamp(1.75rem,4vw,2.75rem)]">
                {t('catalogue.heading')}
            </h1>

            {/* Category chips */}
            <div className="mb-6 flex flex-wrap justify-center gap-2">
                <Link href={hrefWith({ category: undefined })} className={chip(!activeCategory)}>
                    {t('catalogue.all')}
                </Link>
                {categories.map((c) => (
                    <Link key={c.id} href={hrefWith({ category: c.slug })} className={chip(activeCategory === c.slug)}>
                        {localized(c, 'name')}
                    </Link>
                ))}
            </div>

            {/* Toolbar: search · offers toggle · sort */}
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <form onSubmit={submitSearch} className="relative min-w-0 flex-1 sm:max-w-xs">
                    <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-brand-gold" />
                    <input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={t('catalogue.searchPlaceholder')}
                        aria-label={t('nav.search')}
                        className="w-full rounded-full border border-brand-gold/30 bg-white py-2 ps-9 pe-4 text-sm text-brand-teal placeholder:text-brand-teal/40 focus:border-brand-teal focus:outline-none"
                    />
                </form>

                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => go({ on_sale: filters.on_sale ? undefined : '1' })}
                        aria-pressed={filters.on_sale}
                        className={`inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium transition-colors ${
                            filters.on_sale
                                ? 'border-brand-gold bg-brand-gold text-white'
                                : 'border-brand-gold/30 text-brand-teal hover:bg-brand-gold/10'
                        }`}
                    >
                        <Tag className="size-4" />
                        {t('catalogue.offersOnly')}
                    </button>

                    <label className="sr-only" htmlFor="catalogue-sort">
                        {t('catalogue.sortLabel')}
                    </label>
                    <select
                        id="catalogue-sort"
                        value={filters.sort}
                        onChange={(e) => go({ sort: e.target.value === 'newest' ? undefined : e.target.value })}
                        className="rounded-full border border-brand-gold/30 bg-white px-4 py-2 text-sm text-brand-teal focus:border-brand-teal focus:outline-none"
                    >
                        <option value="newest">{t('catalogue.sortNewest')}</option>
                        <option value="price_asc">{t('catalogue.sortPriceAsc')}</option>
                        <option value="price_desc">{t('catalogue.sortPriceDesc')}</option>
                        <option value="name">{t('catalogue.sortName')}</option>
                    </select>
                </div>
            </div>

            {/* Result count + clear */}
            <div className="mb-6 flex items-center justify-between gap-3 text-sm text-brand-teal/70">
                <span>{t('catalogue.resultCount', { n: products.total })}</span>
                {hasFilters && (
                    <Link href="/shop" className="inline-flex items-center gap-1 text-brand-gold hover:text-brand-teal">
                        <X className="size-3.5" />
                        {t('catalogue.clearFilters')}
                    </Link>
                )}
            </div>

            {products.data.length === 0 ? (
                <p className="py-16 text-center text-brand-teal/60">
                    {filters.q ? t('catalogue.noResults') : t('shop.empty')}
                </p>
            ) : (
                <div className="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                    {products.data.map((p) => (
                        <ProductCard key={p.id} product={p} />
                    ))}
                </div>
            )}

            <StorePagination paginator={products} />
        </StoreLayout>
    );
}
