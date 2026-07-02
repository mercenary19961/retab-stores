import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import ar from './ar';
import en from './en';

/**
 * Initialize at 'ar' (AR-first storefront). The active language is applied by
 * LanguageContext after hydration from the server-shared session locale — the
 * session cookie is the single source of truth (no localStorage).
 *
 * NOTE: renderToString does not run effects, so once runtime SSR is enabled the
 * SSR entry must call i18n.changeLanguage(locale) before render to honour the
 * session on the server. AR default keeps SSR correct for the common case.
 */
i18n.use(initReactI18next).init({
    resources: {
        ar: { translation: ar },
        en: { translation: en },
    },
    lng: 'ar',
    fallbackLng: 'ar',
    supportedLngs: ['ar', 'en'],
    interpolation: {
        escapeValue: false,
    },
    returnObjects: true,
});

export default i18n;
