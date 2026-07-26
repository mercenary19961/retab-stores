import { expect, test } from './fixtures';

// The AR⇄EN switch flips document direction immediately and persists server-side
// (session + cookie) across navigations.

test('toggling language flips direction and persists across pages', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    await page.getByTestId('lang-toggle').click();

    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');

    // A fresh navigation still renders EN (the server read the locale cookie).
    await page.goto('/shop');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
});
