import { createInstance } from 'i18next';
import { initReactI18next } from 'react-i18next';
import ar from './ar';
import en from './en';

/**
 * A SEPARATE i18next instance for the admin panel, decoupled from the storefront
 * one. The storefront defaults to Arabic (session locale); the admin defaults to
 * English (client decision) and is toggled independently, persisted to
 * localStorage. Keeping a distinct instance means the storefront's Arabic session
 * locale can't clobber the admin's English default on load, and vice-versa.
 * Shares the same ar/en bundles (which carry the `admin.*` keys).
 */
const adminI18n = createInstance();

adminI18n.use(initReactI18next).init({
    resources: {
        ar: { translation: ar },
        en: { translation: en },
    },
    lng: 'en',
    fallbackLng: 'en',
    supportedLngs: ['ar', 'en'],
    interpolation: { escapeValue: false },
    returnObjects: true,
});

export default adminI18n;
