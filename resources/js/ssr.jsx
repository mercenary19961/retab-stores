/* prettier-ignore */
import {
createInertiaApp
} from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';
import i18n from './i18n';
import { LanguageProvider } from './contexts/LanguageContext';

createServer((page) => {
    // renderToString runs no effects, so LanguageContext can't apply the locale
    // server-side — set it here from the shared session locale so SSR output
    // matches what the client will hydrate.
    i18n.changeLanguage(page.props?.locale ?? 'ar');

    return createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        resolve: (name) => {
            const pages = import.meta.glob('./pages/**/*.tsx', {
                eager: true,
            });
            // Inertia v3 no longer auto-unwraps the default export — return the
            // component itself, not the module object.
            return pages[`./pages/${name}.tsx`].default;
        },
        // prettier-ignore
        setup: ({ App, props }) => (
            <LanguageProvider initialLocale={page.props?.locale}>
                <App {...props} />
            </LanguageProvider>
        ),
    });
});
