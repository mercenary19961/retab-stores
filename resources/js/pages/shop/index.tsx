import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import StoreLayout from '@/layouts/store-layout';
import StoreHero from '@/components/store/hero';
import BestSellers from '@/components/store/best-sellers';
import CategoriesSection from '@/components/store/categories-section';
import PrimaryBanner from '@/components/store/primary-banner';
import NewArrivals from '@/components/store/new-arrivals';
import ClientReviews from '@/components/store/client-reviews';
import FooterBanner from '@/components/store/footer-banner';

interface ProductCard {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    price: number;
    sale_price: number | null;
    effective_price: number;
    on_sale: boolean;
    is_featured: boolean;
    image: string | null;
    category: { name_ar: string; name_en: string | null; slug: string } | null;
}

interface FeaturedCategory {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    image: string | null;
}

interface ReviewItem {
    id: number;
    author_name: string;
    body: string;
    rating: number;
}

export default function ShopIndex({
    bestSellers = [],
    newArrivals = [],
    featuredCategories = [],
    reviews = [],
}: {
    bestSellers?: ProductCard[];
    newArrivals?: ProductCard[];
    featuredCategories?: FeaturedCategory[];
    reviews?: ReviewItem[];
}) {
    const { t } = useTranslation();

    return (
        <StoreLayout bare>
            <Head title={t('shop.headTitle')}>
                <meta name="description" content={t('shop.metaDescription')} />
                <meta property="og:title" content={t('shop.headTitle')} />
                <meta property="og:type" content="website" />
            </Head>

            <StoreHero />
            <BestSellers products={bestSellers} />
            <CategoriesSection categories={featuredCategories} />
            <PrimaryBanner />
            <NewArrivals products={newArrivals} />
            <ClientReviews reviews={reviews} />
            <FooterBanner />
        </StoreLayout>
    );
}
