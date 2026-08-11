import ContactInfo from '@/components/store/contact-info';
import ContactSubmit from '@/components/store/contact-submit';
import StoreLayout from '@/layouts/store-layout';
import { type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

/**
 * Designed Contact Us page. `bare` because the sections are full-bleed and each
 * brings its own container, exactly like the About page and the homepage.
 */
export default function Contact() {
    const { t } = useTranslation();
    const { ogImage } = usePage<SharedData>().props;

    return (
        <StoreLayout bare>
            <Head title={t('contact.headTitle')}>
                <meta name="description" content={t('contact.metaDescription')} />
                <meta property="og:title" content={t('contact.headTitle')} />
                <meta property="og:description" content={t('contact.metaDescription')} />
                <meta property="og:type" content="website" />
                <meta property="og:image" content={ogImage} />
                <meta property="og:image:width" content="1200" />
                <meta property="og:image:height" content="630" />
                <meta name="twitter:card" content="summary_large_image" />
            </Head>

            <ContactInfo />
            <ContactSubmit />
        </StoreLayout>
    );
}
