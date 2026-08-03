import ProductCarousel, { type CarouselProduct } from '@/components/store/product-carousel';
import { useTranslation } from 'react-i18next';

/** "وصل حديثاً" homepage strip — newest products, each with a "صنف جديد" badge. */
export default function NewArrivals({ products }: { products: CarouselProduct[] }) {
    const { t } = useTranslation();
    return <ProductCarousel title={t('newArrivals.title')} products={products} badgeLabel={t('newArrivals.badge')} />;
}
