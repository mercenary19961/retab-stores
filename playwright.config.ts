import { defineConfig } from '@playwright/test';
import path from 'node:path';

// Where the suite points:
//   • default (no QA_BASE_URL) → a throwaway, freshly-seeded SQLite app that
//     Playwright boots itself (see webServer below). Deterministic fixture data
//     (CatalogSeeder) and it never touches the MariaDB dev DB.
//   • QA_BASE_URL set (e.g. the Railway URL) → runs the assertions against that
//     deployment instead and skips the local server.
const LOCAL = 'http://127.0.0.1:8100';
const BASE = process.env.QA_BASE_URL || LOCAL;
const bootLocalServer = !process.env.QA_BASE_URL;

// Throwaway SQLite file for the E2E run (gitignored). Forward slashes so the
// path is happy on Windows PHP too.
const e2eDb = path.resolve('database/e2e.sqlite').replace(/\\/g, '/');

export default defineConfig({
    testDir: './e2e',
    timeout: 45_000,
    expect: { timeout: 10_000 },
    // The PHP built-in server (artisan serve) handles one request at a time, and a
    // single SQLite file has one writer — so run serially for determinism.
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : [['list']],
    use: {
        baseURL: BASE,
        navigationTimeout: 30_000,
        actionTimeout: 15_000,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { browserName: 'chromium', viewport: { width: 1440, height: 900 } },
        },
    ],
    webServer: bootLocalServer
        ? {
              // Reseed a fresh SQLite DB, then serve it. DB_* overrides are passed via
              // env (Laravel's real env wins over .env), so the dev DB is untouched.
              command:
                  'php artisan migrate:fresh --seed --force && php artisan serve --host=127.0.0.1 --port=8100',
              url: LOCAL,
              timeout: 120_000,
              reuseExistingServer: false,
              stdout: 'pipe',
              stderr: 'pipe',
              env: { DB_CONNECTION: 'sqlite', DB_DATABASE: e2eDb },
          }
        : undefined,
});
