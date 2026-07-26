import { expect, test } from './fixtures';

// Browsing the storefront: home, catalogue, category filter, search, product page.
// Runs against the seeded fixture catalogue (CatalogSeeder) on AR-first pages.

test('home renders with brand nav and product links', async ({ page }) => {
    await page.goto('/');

    // AR-first: the document is right-to-left.
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');

    // The brand logo and at least one product link are present.
    await expect(page.getByRole('img', { name: /retab|رطاب/i }).first()).toBeVisible();
    await expect(page.locator('a[href^="/products/"]').first()).toBeVisible();
});

test('catalogue lists the seeded products', async ({ page }) => {
    await page.goto('/shop');

    // CatalogSeeder seeds 10 active products; all fit on the first page (12/page).
    await expect(page.locator('a[href^="/products/"]').first()).toBeVisible();
    expect(await page.locator('a[href^="/products/"]').count()).toBeGreaterThanOrEqual(8);
});

test('category filter narrows the catalogue', async ({ page }) => {
    await page.goto('/shop?category=sukkari');

    // The Sukkari product is in this category; an out-of-category one is not.
    await expect(page.locator('a[href="/products/sukkari-1kg"]').first()).toBeVisible();
    await expect(page.locator('a[href="/products/ajwa-1kg"]')).toHaveCount(0);
});

test('search filters by name', async ({ page }) => {
    await page.goto('/shop?q=Khalas');

    await expect(page.locator('a[href="/products/khalas-1kg"]').first()).toBeVisible();
    await expect(page.locator('a[href="/products/sukkari-1kg"]')).toHaveCount(0);
});

test('product page shows details and an add-to-cart control', async ({ page }) => {
    await page.goto('/products/sukkari-1kg');

    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    // Seeded price is 75 SAR.
    await expect(page.getByText(/75/).first()).toBeVisible();
    await expect(page.getByTestId('add-to-cart')).toBeVisible();
});

test('an unknown product slug 404s', async ({ page }) => {
    const res = await page.goto('/products/definitely-not-a-real-slug');
    expect(res?.status()).toBe(404);
});
