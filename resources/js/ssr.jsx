/* prettier-ignore */
import {
createInertiaApp
} from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';
import './i18n';
import { LanguageProvider } from './contexts/LanguageContext';

createServer((page) =>
    createInertiaApp({
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
            <LanguageProvider>
                <App {...props} />
            </LanguageProvider>
        ),
    }),
);
