import { expect, test } from './fixtures';

// Guest checkout, end to end, using bank transfer — the one method that doesn't
// redirect out to an external gateway (card/Tamara need live keys we don't have in
// E2E). Proves the whole path: cart → address form → order placed → confirmation.

test('guest can place a bank-transfer order', async ({ page }) => {
    // Put an item in the cart.
    await page.goto('/products/sukkari-1kg');
    await Promise.all([
        page.waitForResponse((r) => r.url().includes('/cart') && r.request().method() === 'POST'),
        page.getByTestId('add-to-cart').click(),
    ]);

    await page.goto('/checkout');

    // Required fields (country defaults to SA, payment defaults to bank_transfer).
    await page.getByTestId('customer_name').fill('E2E Buyer');
    await page.getByTestId('customer_phone').fill('0512345678');
    await page.getByTestId('city').fill('Riyadh');
    await page.locator('input[name="payment_method"][value="bank_transfer"]').check();

    await Promise.all([
        page.waitForURL(/\/orders\/.+/),
        page.getByTestId('place-order').click(),
    ]);

    // Landed on the confirmation page for a real order…
    await expect(page).toHaveURL(/\/orders\/.+/);
    // …and bank-transfer orders show the store IBAN to pay into (seeded settings).
    await expect(page.getByText(/SA97/)).toBeVisible();
});

test('checkout redirects to the cart when nothing is in it', async ({ page }) => {
    const res = await page.goto('/checkout');
    // CheckoutController::show bounces an empty cart back to /cart.
    await expect(page).toHaveURL(/\/cart$/);
    expect(res?.status()).toBeLessThan(400);
});
