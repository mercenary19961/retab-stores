import { expect, test } from './fixtures';

// Regression guard for the auth/storefront dark-leak bug: dark mode is ADMIN-only
// (scoped to `.admin-shell dark`), so the customer-facing pages must stay light
// even when the visitor's OS prefers dark — the document root must never get `.dark`.
test.use({ colorScheme: 'dark' });

for (const path of ['/', '/login', '/register', '/shop']) {
    test(`public app stays light under OS-dark · ${path}`, async ({ page }) => {
        await page.goto(path, { waitUntil: 'networkidle' });
        await expect(page.locator('html')).not.toHaveClass(/\bdark\b/);
    });
}
