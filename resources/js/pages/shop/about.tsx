import AboutBanner from '@/components/store/about-banner';
import AboutWhoWeAre from '@/components/store/about-who-we-are';
import StoreLayout from '@/layouts/store-layout';
import { type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

/**
 * Designed About Us page. `bare` because the banner is full-bleed; each section
 * below it brings its own container, exactly like the homepage.
 */
export default function About() {
    const { t } = useTranslation();
    const { ogImage } = usePage<SharedData>().props;

    return (
        <StoreLayout bare>
            <Head title={t('about.headTitle')}>
                <meta name="description" content={t('about.metaDescription')} />
                <meta property="og:title" content={t('about.headTitle')} />
                <meta property="og:description" content={t('about.metaDescription')} />
                <meta property="og:type" content="website" />
                <meta property="og:image" content={ogImage} />
                <meta property="og:image:width" content="1200" />
                <meta property="og:image:height" content="630" />
                <meta name="twitter:card" content="summary_large_image" />
            </Head>

            <AboutBanner />
            <AboutWhoWeAre />
        </StoreLayout>
    );
}
