import { defineConfig } from '@playwright/test';

// E2E / visual-QA config. Runs against the deployed site by default; override
// with QA_BASE_URL (e.g. http://localhost:8000) to point at a local server.
export default defineConfig({
    testDir: './e2e',
    timeout: 45_000,
    expect: { timeout: 10_000 },
    fullyParallel: true,
    workers: 3,
    reporter: [['list']],
    use: {
        baseURL: process.env.QA_BASE_URL || 'https://retab-website-production.up.railway.app',
        navigationTimeout: 30_000,
        actionTimeout: 15_000,
    },
    projects: [
        {
            name: 'chromium',
            use: { browserName: 'chromium', viewport: { width: 1440, height: 900 } },
        },
    ],
});
