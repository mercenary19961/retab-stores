/* prettier-ignore */
import {
createInertiaApp
} from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';

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
        setup: ({ App, props }) => <App {...props} />,
    }),
);
