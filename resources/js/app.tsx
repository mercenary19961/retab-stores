import '../css/app.css';
import i18n from './i18n';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { I18nextProvider } from 'react-i18next';
import { route as routeFn } from 'ziggy-js';
import { LanguageProvider } from './contexts/LanguageContext';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    // Inertia v3 dropped the auto-unwrap of the page module's default export,
    // so resolvePageComponent's Promise<{ default: Component }> must be
    // unwrapped to the component with .then((m) => m.default).
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx'),
        ).then((m) => m.default),
    setup({ el, App, props }) {
        const root = createRoot(el);

        const locale = (props.initialPage.props as { locale?: 'ar' | 'en' }).locale;

        root.render(
            // Explicit storefront instance so admin's separate i18next instance
            // (a nested I18nextProvider) can't hijack the global default.
            <I18nextProvider i18n={i18n}>
                <LanguageProvider initialLocale={locale}>
                    <App {...props} />
                </LanguageProvider>
            </I18nextProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
