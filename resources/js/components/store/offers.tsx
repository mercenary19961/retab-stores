import ProductCarousel, { type CarouselProduct } from '@/components/store/product-carousel';
import { useTranslation } from 'react-i18next';

/** "العروض" homepage strip — active discounted products (see ShopController).
 *  Self-hides when there are no offers (the carousel renders nothing when empty). */
export default function Offers({ products }: { products: CarouselProduct[] }) {
    const { t } = useTranslation();
    return <ProductCarousel title={t('offers.title')} products={products} mirrorPattern />;
}
