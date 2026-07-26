import { expect, test } from './fixtures';

// Cart lifecycle: add from the product page, change quantity, remove. Each test
// starts from a fresh browser context, so the cart begins empty.

async function addSukkari(page: import('@playwright/test').Page) {
    await page.goto('/products/sukkari-1kg');
    await Promise.all([
        page.waitForResponse((r) => r.url().includes('/cart') && r.request().method() === 'POST'),
        page.getByTestId('add-to-cart').click(),
    ]);
}

test('add a product to the cart', async ({ page }) => {
    await addSukkari(page);

    await page.goto('/cart');
    await expect(page.getByTestId('cart-item')).toHaveCount(1);
    // Seeded unit price 75 → subtotal 75 at quantity 1.
    await expect(page.getByTestId('cart-subtotal')).toContainText('75');
});

test('updating quantity recomputes the total', async ({ page }) => {
    await addSukkari(page);
    await page.goto('/cart');

    await Promise.all([
        page.waitForResponse((r) => r.url().includes('/cart/items/') && r.request().method() === 'PATCH'),
        page.getByTestId('cart-qty').fill('2'),
    ]);

    // 75 × 2 = 150.
    await expect(page.getByTestId('cart-subtotal')).toContainText('150');
});

test('removing the last item empties the cart', async ({ page }) => {
    await addSukkari(page);
    await page.goto('/cart');

    await page.getByTestId('cart-remove').click();

    await expect(page.getByTestId('cart-item')).toHaveCount(0);
});
