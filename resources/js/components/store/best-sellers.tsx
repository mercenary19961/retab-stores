import { useTranslation } from 'react-i18next';
import ProductCarousel, { type CarouselProduct } from '@/components/store/product-carousel';

/** "الأكثر مبيعاً" homepage strip — ranked by real sales (see ShopController). */
export default function BestSellers({ products }: { products: CarouselProduct[] }) {
    const { t } = useTranslation();
    return <ProductCarousel title={t('bestSellers.title')} products={products} />;
}
