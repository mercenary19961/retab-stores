import { defineConfig } from 'vitest/config';
import { fileURLToPath } from 'node:url';

// Vitest config for the pure front-end helpers in resources/js/lib. They have no
// DOM dependency, so the default `node` environment is enough (no jsdom). The `@`
// alias mirrors the app so test imports resolve the same way.
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'node',
        include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
    },
});
