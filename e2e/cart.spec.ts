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

// The quantity control is a −/+ stepper, not a text field: it writes on click so
// a typed "10" can't PATCH once per character. Clicking is also what a real
// shopper does, so the test drives the same path they do.
test('updating quantity recomputes the total', async ({ page }) => {
    await addSukkari(page);
    await page.goto('/cart');

    await Promise.all([
        page.waitForResponse((r) => r.url().includes('/cart/items/') && r.request().method() === 'PATCH'),
        page.getByTestId('cart-qty-increase').click(),
    ]);

    await expect(page.getByTestId('cart-qty')).toContainText('2');
    // 75 × 2 = 150.
    await expect(page.getByTestId('cart-subtotal')).toContainText('150');
});

test('decrease is blocked at one so the stepper cannot silently empty the cart', async ({ page }) => {
    await addSukkari(page);
    await page.goto('/cart');

    await expect(page.getByTestId('cart-qty')).toContainText('1');
    await expect(page.getByTestId('cart-qty-decrease')).toBeDisabled();
});

test('removing the last item empties the cart', async ({ page }) => {
    await addSukkari(page);
    await page.goto('/cart');

    await page.getByTestId('cart-remove').click();

    await expect(page.getByTestId('cart-item')).toHaveCount(0);
});

// The summary must show the shipping line and a total that already includes it —
// the page used to only say "shipping is added at checkout".
test('the summary shows shipping and a total including it', async ({ page }) => {
    await addSukkari(page);
    await page.goto('/cart');

    await expect(page.getByTestId('cart-shipping')).toBeVisible();

    // ⚠️ Match the FIRST number rather than stripping non-digits: the Arabic
    // currency symbol "ر.س" contains periods, so stripping would leave "11.50.."
    // and parse as NaN.
    const amount = async (testId: string) => {
        const text = await page.getByTestId(testId).innerText();
        return Number(text.match(/\d+(?:\.\d+)?/)?.[0]);
    };

    const subtotal = await amount('cart-subtotal');
    const total = await amount('cart-total');
    expect(Number.isNaN(subtotal)).toBe(false);
    expect(Number.isNaN(total)).toBe(false);
    // Flat GCC shipping is seeded at 25, so the total must exceed the subtotal.
    expect(total).toBeGreaterThan(subtotal);
});

test('an empty cart offers a way back into the catalogue', async ({ page }) => {
    await page.goto('/cart');

    await expect(page.getByTestId('cart-item')).toHaveCount(0);
    // Empty state links to the shop rather than dead-ending.
    await expect(page.locator('a[href="/shop"]').first()).toBeVisible();
});

test('a bogus coupon code is rejected and nothing is discounted', async ({ page }) => {
    await addSukkari(page);
    await page.goto('/cart');

    await page.getByTestId('cart-coupon-input').fill('DEFINITELY-NOT-A-CODE');
    await Promise.all([
        page.waitForResponse((r) => r.url().includes('/cart/coupon') && r.request().method() === 'POST'),
        page.getByTestId('cart-coupon-apply').click(),
    ]);

    // Still no discount line, and the coupon input is still offered.
    await expect(page.getByTestId('cart-discount')).toHaveCount(0);
    await expect(page.getByTestId('cart-coupon-input')).toBeVisible();
});
