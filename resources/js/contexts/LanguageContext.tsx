import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

export type Language = 'ar' | 'en';

interface LanguageContextType {
    language: Language;
    isRTL: boolean;
    toggleLanguage: () => void;
    setLanguage: (lang: Language) => void;
}

const LanguageContext = createContext<LanguageContextType | undefined>(undefined);

export function LanguageProvider({ children, initialLocale }: { children: ReactNode; initialLocale?: Language }) {
    const { i18n } = useTranslation();
    // Seeded from the server-shared session locale, passed down by app.tsx /
    // ssr.jsx (the provider sits ABOVE <App>, so usePage() is unavailable here —
    // calling it crashes the SSR render). AR-first default; the session cookie
    // is the single source of truth — no localStorage involved.
    const [language, setLanguageState] = useState<Language>(initialLocale ?? 'ar');
    const isRTL = language === 'ar';

    useEffect(() => {
        if (typeof document !== 'undefined') {
            document.documentElement.dir = isRTL ? 'rtl' : 'ltr';
            document.documentElement.lang = language;
        }
        i18n.changeLanguage(language);
    }, [language, isRTL, i18n]);

    const syncLocale = (lang: Language) => {
        // Persist to the server session without an Inertia visit.
        if (typeof document === 'undefined') return;
        const xsrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
        fetch(`/locale/${lang}`, {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        }).catch(() => {});
    };

    const toggleLanguage = () => {
        const next: Language = language === 'ar' ? 'en' : 'ar';
        setLanguageState(next);
        syncLocale(next);
    };

    const setLanguage = (lang: Language) => {
        setLanguageState(lang);
        syncLocale(lang);
    };

    return (
        <LanguageContext.Provider value={{ language, isRTL, toggleLanguage, setLanguage }}>
            {children}
        </LanguageContext.Provider>
    );
}

export function useLanguage() {
    const ctx = useContext(LanguageContext);
    if (ctx === undefined) {
        throw new Error('useLanguage must be used within a LanguageProvider');
    }
    return ctx;
}
